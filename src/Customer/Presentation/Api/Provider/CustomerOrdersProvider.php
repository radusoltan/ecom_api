<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\Query\GetCustomerOrdersQuery;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProviderInterface<array<int, array<string, mixed>>>
 */
final readonly class CustomerOrdersProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $customerId = CustomerId::fromString($uriVariables['id']);

        // Extract tenant ID from context (set by TenantContextMiddleware)
        $tenantId = $context['tenant_id'] ?? throw new \InvalidArgumentException('Tenant ID is required');
        $tenantId = TenantId::fromString($tenantId);

        $query = new GetCustomerOrdersQuery($customerId, $tenantId);

        $envelope = $this->queryBus->dispatch($query);

        $handledStamp = $envelope->last(HandledStamp::class);

        /** @var array<int, array<string, mixed>> $result */
        $result = $handledStamp?->getResult() ?? [];

        return $result;
    }
}
