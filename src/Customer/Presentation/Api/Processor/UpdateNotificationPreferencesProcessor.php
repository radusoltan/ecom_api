<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\UpdatePreferences\UpdatePreferencesCommand;
use App\Customer\Application\DTO\CustomerPreferencesDTO;
use App\Customer\Application\Query\GetCustomerPreferences\GetCustomerPreferencesQuery;
use App\Customer\Presentation\Api\Resource\NotificationPreferencesResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Update Notification Preferences Processor.
 *
 * Updates customer notification preferences.
 *
 * @implements ProcessorInterface<NotificationPreferencesResource, NotificationPreferencesResource>
 */
final readonly class UpdateNotificationPreferencesProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NotificationPreferencesResource
    {
        if (!$data instanceof NotificationPreferencesResource) {
            throw new BadRequestHttpException('Expected NotificationPreferencesResource');
        }

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

        // Validate all required fields are provided
        if (null === $data->newsletterSubscribed || null === $data->marketingEmailsAllowed
            || null === $data->smsNotificationsAllowed || null === $data->orderNotificationsEnabled
            || null === $data->promotionNotificationsEnabled) {
            throw new BadRequestHttpException('All notification preference fields are required');
        }

        // Build preferences array
        $preferences = [
            'newsletterSubscribed' => $data->newsletterSubscribed,
            'marketingEmailsAllowed' => $data->marketingEmailsAllowed,
            'smsNotificationsAllowed' => $data->smsNotificationsAllowed,
            'orderNotificationsEnabled' => $data->orderNotificationsEnabled,
            'promotionNotificationsEnabled' => $data->promotionNotificationsEnabled,
        ];

        // Add optional language and currency preferences
        if (null !== $data->preferredLanguage) {
            $preferences['preferredLanguage'] = $data->preferredLanguage;
        }
        if (null !== $data->preferredCurrency) {
            $preferences['preferredCurrency'] = $data->preferredCurrency;
        }

        // Create command
        $command = new UpdatePreferencesCommand(
            customerId: $customerId,
            tenantId: $tenantId->toString(),
            preferences: $preferences
        );

        $this->commandBus->dispatch($command);

        // Retrieve updated preferences
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
            throw new \RuntimeException('Preferences not found after update');
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
