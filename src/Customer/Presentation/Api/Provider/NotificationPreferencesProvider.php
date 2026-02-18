<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\DTO\CustomerPreferencesDTO;
use App\Customer\Application\Query\GetCustomerPreferences\GetCustomerPreferencesQuery;
use App\Customer\Presentation\Api\Resource\NotificationPreferencesResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Notification Preferences Provider.
 *
 * Provides customer notification preferences.
 *
 * @implements ProviderInterface<NotificationPreferencesResource>
 */
final readonly class NotificationPreferencesProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NotificationPreferencesResource
    {
        // Extract customerId from URI
        if (!isset($uriVariables['customerId'])) {
            throw new BadRequestHttpException('Customer ID is required');
        }

        $customerId = $uriVariables['customerId'];

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Dispatch query
        $query = new GetCustomerPreferencesQuery(
            customerId: $customerId,
            tenantId: $tenantId->toString()
        );

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var CustomerPreferencesDTO|null $dto */
        $dto = $handledStamp->getResult();

        if (null === $dto) {
            throw new NotFoundHttpException('Customer preferences not found');
        }

        // Map DTO to resource
        return $this->mapDtoToResource($dto, $customerId);
    }

    private function mapDtoToResource(CustomerPreferencesDTO $dto, string $customerId): NotificationPreferencesResource
    {
        $resource = new NotificationPreferencesResource();
        $resource->customerId = $customerId;
        $resource->newsletterSubscribed = $dto->newsletterSubscribed;
        $resource->marketingEmailsAllowed = $dto->marketingEmailsAllowed;
        $resource->smsNotificationsAllowed = $dto->smsNotificationsAllowed;
        $resource->orderNotificationsEnabled = $dto->orderNotificationsEnabled;
        $resource->promotionNotificationsEnabled = $dto->promotionNotificationsEnabled;
        $resource->preferredLanguage = $dto->preferredLanguage;
        $resource->preferredCurrency = $dto->preferredCurrency;

        return $resource;
    }
}
