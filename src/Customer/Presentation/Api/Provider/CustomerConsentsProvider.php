<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\DTO\CustomerConsentsDTO;
use App\Customer\Application\Query\GetCustomerConsents\GetCustomerConsentsQuery;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Presentation\Api\Resource\CustomerConsentResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Customer Consents Provider.
 *
 * Provides current GDPR consent status for a customer.
 *
 * @implements ProviderInterface<CustomerConsentResource>
 */
final readonly class CustomerConsentsProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomerConsentResource
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

        // Dispatch query
        $query = new GetCustomerConsentsQuery(
            customerId: $customerId,
            tenantId: $tenantId
        );

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var CustomerConsentsDTO|null $dto */
        $dto = $handledStamp->getResult();

        if (null === $dto) {
            throw new NotFoundHttpException('Customer consents not found');
        }

        // Map DTO to resource
        return $this->mapDtoToResource($dto);
    }

    private function mapDtoToResource(CustomerConsentsDTO $dto): CustomerConsentResource
    {
        $resource = new CustomerConsentResource();
        $resource->customerId = $dto->customerId;
        $resource->marketingEmail = $dto->marketingEmail;
        $resource->marketingSms = $dto->marketingSms;
        $resource->thirdPartySharing = $dto->thirdPartySharing;
        $resource->analyticsTracking = $dto->analyticsTracking;
        $resource->lastUpdatedAt = $dto->lastUpdatedAt->format(\DateTimeInterface::ATOM);

        return $resource;
    }
}
