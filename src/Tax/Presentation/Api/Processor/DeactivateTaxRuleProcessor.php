<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Command\DeactivateTaxRule;
use App\Tax\Application\Query\GetTaxRuleById;
use App\Tax\Domain\ValueObject\TaxRuleId;
use App\Tax\Presentation\Api\Resource\TaxRuleResource;
use App\Tax\Presentation\Api\Transformer\TaxRuleResourceTransformer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Processor for deactivating tax rules.
 */
final class DeactivateTaxRuleProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        MessageBusInterface $queryBus,
        private readonly TaxRuleResourceTransformer $transformer,
        private readonly RequestStack $requestStack
    ) {
        $this->messageBus = $queryBus;
    }

    /**
     * @param TaxRuleResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaxRuleResource
    {
        // Get ID from URI variables
        $id = $uriVariables['id'] ?? null;
        if (!$id) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('Tax rule ID is required');
        }

        // Get tenant ID from X-Tenant-ID header
        $request = $this->requestStack->getCurrentRequest();
        $tenantIdString = $request?->headers->get('X-Tenant-ID');

        if (empty($tenantIdString)) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('X-Tenant-ID header is required');
        }

        // Create command
        $command = new DeactivateTaxRule(
            id: TaxRuleId::fromString($id),
            tenantId: TenantId::fromString($tenantIdString)
        );

        // Dispatch command - deactivate operation should be idempotent
        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();
            // Domain exceptions should be converted to validation errors (422)
            if ($previous instanceof \InvalidArgumentException) {
                throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException($previous->getMessage(), $previous);
            }
            // DomainException for already inactive state is acceptable (idempotent)
            // Just continue and return the current state
            if (!($previous instanceof \DomainException)) {
                throw $e;
            }
        } catch (\InvalidArgumentException $e) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        // Fetch updated tax rule
        $query = new GetTaxRuleById(
            id: TaxRuleId::fromString($id),
            tenantId: TenantId::fromString($tenantIdString)
        );

        $dto = $this->handle($query);

        if (null === $dto) {
            throw new NotFoundHttpException('Tax rule not found');
        }

        return $this->transformer->fromDTO($dto);
    }
}
