<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\Query\GetCustomerTransactions\GetCustomerTransactionsQuery;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Loyalty Transaction Collection Provider.
 *
 * Provides paginated loyalty point transaction history for a customer.
 *
 * @implements ProviderInterface<object>
 */
final readonly class LoyaltyTransactionCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext,
    ) {
    }

    /**
     * @return iterable<object>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        // Extract customer ID from URI
        if (!isset($uriVariables['customerId'])) {
            throw new BadRequestHttpException('Customer ID is required');
        }

        $customerId = CustomerId::fromString($uriVariables['customerId']);

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Extract pagination parameters from query string
        $filters = $context['filters'] ?? [];
        $page = isset($filters['page']) ? (int) $filters['page'] : 1;
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 20;

        // Validate pagination parameters
        if ($page < 1) {
            $page = 1;
        }

        if ($limit < 1 || $limit > 100) {
            $limit = 20;
        }

        // Dispatch query
        $query = new GetCustomerTransactionsQuery(
            customerId: $customerId,
            tenantId: TenantId::fromString($tenantId->toString()),
            page: $page,
            limit: $limit
        );

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var array<int, \App\Customer\Application\DTO\LoyaltyPointTransactionDTO> $transactions */
        $transactions = $handledStamp->getResult();

        // Return DTOs directly (they are objects)
        return $transactions;
    }
}
