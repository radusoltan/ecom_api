<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\DTO\ConsentHistoryDTO;
use App\Customer\Application\Query\GetConsentHistory\GetConsentHistoryQuery;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Presentation\Api\Resource\CustomerConsentResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Consent History Provider.
 *
 * Provides paginated consent change history for audit purposes.
 *
 * @implements ProviderInterface<object>
 */
final readonly class ConsentHistoryProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext
    ) {
    }

    /**
     * @return CustomerConsentResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Extract customerId from URI
        if (!isset($uriVariables['customerId'])) {
            throw new BadRequestHttpException('Customer ID is required');
        }

        $customerId = CustomerId::fromString($uriVariables['customerId']);

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Get pagination parameters from query
        $page = (int) ($context['filters']['page'] ?? 1);
        $limit = (int) ($context['filters']['limit'] ?? 20);

        // Enforce maximum limit
        if ($limit > 100) {
            $limit = 100;
        }

        // Dispatch query
        $query = new GetConsentHistoryQuery(
            customerId: $customerId,
            tenantId: $tenantId,
            page: $page,
            limit: $limit
        );

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var ConsentHistoryDTO[] $historyDTOs */
        $historyDTOs = $handledStamp->getResult();

        // Map DTOs to resources
        return array_map(
            fn (ConsentHistoryDTO $dto) => $this->mapDtoToResource($dto),
            $historyDTOs
        );
    }

    private function mapDtoToResource(ConsentHistoryDTO $dto): CustomerConsentResource
    {
        $resource = new CustomerConsentResource();
        $resource->id = $dto->id;
        $resource->consentType = $dto->consentType;
        $resource->consentTypeLabel = $dto->consentTypeLabel;
        $resource->granted = $dto->granted;
        $resource->ipAddress = $dto->ipAddress;
        $resource->userAgent = $dto->userAgent;
        $resource->createdAt = $dto->createdAt->format(\DateTimeInterface::ATOM);

        return $resource;
    }
}
