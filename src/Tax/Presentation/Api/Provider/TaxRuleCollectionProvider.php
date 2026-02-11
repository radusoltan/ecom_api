<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Query\GetAllTaxRules;
use App\Tax\Presentation\Api\Transformer\TaxRuleResourceTransformer;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Provider for retrieving tax rule collections with pagination.
 *
 * API Platform handles pagination automatically by slicing the returned array.
 * Default: 30 items per page as configured in TaxRuleResource.
 */
final class TaxRuleCollectionProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
        private readonly TaxRuleResourceTransformer $transformer
    ) {
        $this->messageBus = $queryBus;
    }

    /**
     * @return array<\App\Tax\Presentation\Api\Resource\TaxRuleResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Extract tenant ID from context (injected by TenantContextProvider)
        $tenantId = TenantId::fromString(
            $context['tenant_id'] ?? throw new \InvalidArgumentException('Tenant ID is required')
        );

        // Get query parameters from context filters
        $activeOnly = 'true' === ($context['filters']['activeOnly'] ?? 'false');

        // Create query - return all tax rules (API Platform will paginate the result)
        $query = new GetAllTaxRules(
            tenantId: $tenantId,
            activeOnly: $activeOnly,
            limit: 1000, // reasonable max - API Platform will paginate this
            offset: 0
        );

        // Execute query
        $dtos = $this->handle($query);

        // Transform to resources - API Platform will automatically paginate and wrap in Hydra format
        return $this->transformer->fromDTOs($dtos);
    }
}
