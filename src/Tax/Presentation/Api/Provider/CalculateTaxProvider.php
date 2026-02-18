<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Query\CalculateTax;
use App\Tax\Presentation\Api\Resource\TaxCalculationResource;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Provider for calculating tax.
 */
final class CalculateTaxProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
    ) {
        $this->messageBus = $queryBus;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TaxCalculationResource
    {
        // Get the input resource from context
        $data = $context['data'] ?? null;
        if (!$data instanceof TaxCalculationResource) {
            throw new BadRequestHttpException('Invalid request data');
        }

        // Validate required fields
        if (null === $data->amountInCents) {
            throw new BadRequestHttpException('amountInCents is required');
        }

        if (empty($data->countryCode)) {
            throw new BadRequestHttpException('countryCode is required');
        }

        // Get tenant ID from request headers if not provided
        $tenantId = $data->tenantId;
        if (empty($tenantId)) {
            $request = $context['request'] ?? null;
            if ($request) {
                $tenantId = $request->headers->get('X-Tenant-ID');
            }
        }

        if (empty($tenantId)) {
            throw new BadRequestHttpException('tenantId or X-Tenant-ID header is required');
        }

        // Create query
        $query = new CalculateTax(
            amountInCents: $data->amountInCents,
            countryCode: $data->countryCode,
            regionCode: $data->regionCode,
            tenantId: TenantId::fromString($tenantId)
        );

        // Execute query
        $result = $this->handle($query);

        // Create response resource
        $response = new TaxCalculationResource();
        $response->taxAmount = $result['taxAmount'];
        $response->taxRate = $result['taxRate'];
        $response->jurisdiction = $result['jurisdiction'];
        $response->taxRuleId = $result['taxRuleId'];
        $response->taxRuleName = $result['taxRuleName'];

        return $response;
    }
}
