<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Controller;

use App\Shared\Application\Service\TenantContextInterface;
use App\Tax\Application\Query\CalculateTax;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Tax Calculation Controller.
 *
 * Provides endpoint for tax calculation.
 */
final class TaxCalculationController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly TenantContextInterface $tenantContext,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Calculate tax for given amount and jurisdiction.
     *
     * Request body:
     * {
     *   "amount": 10000,           // Amount in cents (required)
     *   "currency": "EUR",         // Currency code (optional, for reference)
     *   "countryCode": "DE",       // 2-letter country code (required)
     *   "regionCode": "BY",        // Optional region/state code
     *   "category": "standard",    // Tax category (optional, defaults to "standard")
     *   "isB2B": false,           // B2B transaction flag (optional)
     *   "vatNumber": null         // VAT number for B2B reverse charge (optional)
     * }
     *
     * Response:
     * {
     *   "netAmount": 10000,
     *   "taxAmount": 1900,
     *   "grossAmount": 11900,
     *   "taxRate": 19.0,
     *   "reverseCharge": false,
     *   "breakdown": {
     *     "jurisdiction": "DE",
     *     "taxRuleId": "uuid",
     *     "taxRuleName": "Germany Standard VAT"
     *   }
     * }
     */
    #[Route('/api/tax/calculate', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            return new JsonResponse(
                ['error' => 'Tenant context not set'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Parse request body
        $data = json_decode($request->getContent(), true);
        if (null === $data) {
            return new JsonResponse(
                ['error' => 'Invalid JSON'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Validate required fields
        $constraints = new Assert\Collection([
            'amount' => [
                new Assert\NotBlank(),
                new Assert\Type('int'),
                new Assert\Positive(),
            ],
            'currency' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\Length(min: 3, max: 3),
            ]),
            'countryCode' => [
                new Assert\NotBlank(),
                new Assert\Type('string'),
                new Assert\Length(min: 2, max: 2),
            ],
            'regionCode' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\Length(max: 3),
            ]),
            'category' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\Choice(['standard', 'reduced', 'zero', 'exempt']),
            ]),
            'isB2B' => new Assert\Optional([
                new Assert\Type('bool'),
            ]),
            'vatNumber' => new Assert\Optional([
                new Assert\Type('string'),
            ]),
        ]);

        $violations = $this->validator->validate($data, $constraints);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return new JsonResponse(
                ['error' => 'Validation failed', 'violations' => $errors],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Extract and prepare data
        $amountInCents = $data['amount'];
        $countryCode = strtoupper($data['countryCode']);
        $regionCode = isset($data['regionCode']) ? strtoupper($data['regionCode']) : null;

        try {
            // Create and dispatch query
            $query = new CalculateTax(
                amountInCents: $amountInCents,
                countryCode: $countryCode,
                regionCode: $regionCode,
                tenantId: $tenantId
            );

            $envelope = $this->queryBus->dispatch($query);
            $stamp = $envelope->last(HandledStamp::class);

            if (!$stamp instanceof HandledStamp) {
                return new JsonResponse(
                    ['error' => 'No handler found for tax calculation'],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }

            $result = $stamp->getResult();

            // Format response
            $taxAmount = $result['taxAmount'];
            $taxRate = $result['taxRate'];
            $grossAmount = $amountInCents + $taxAmount;

            return new JsonResponse([
                'netAmount' => $amountInCents,
                'taxAmount' => $taxAmount,
                'grossAmount' => $grossAmount,
                'taxRate' => $taxRate,
                'reverseCharge' => false, // TODO: Implement B2B reverse charge logic
                'breakdown' => [
                    'jurisdiction' => $result['jurisdiction'],
                    'taxRuleId' => $result['taxRuleId'],
                    'taxRuleName' => $result['taxRuleName'],
                ],
            ]);
        } catch (\DomainException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_NOT_FOUND
            );
        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Tax calculation failed: '.$e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
