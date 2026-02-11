<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\UpdatePreferences\UpdatePreferencesCommand;
use App\Customer\Presentation\Api\Resource\CustomerPreferencesResource;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Update Customer Preferences Processor.
 *
 * Processes PUT requests to update customer preferences.
 *
 * @implements ProcessorInterface<CustomerPreferencesResource, CustomerPreferencesResource>
 */
final class UpdatePreferencesProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @param CustomerPreferencesResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerPreferencesResource
    {
        $customerId = $uriVariables['customerId'] ?? null;

        if (null === $customerId) {
            throw new \InvalidArgumentException('Customer ID is required');
        }

        // Get tenant ID from request header
        $request = $this->requestStack->getCurrentRequest();
        $tenantId = $request?->headers->get('X-Tenant-ID');

        if (null === $tenantId) {
            throw new \InvalidArgumentException('Tenant ID is required');
        }

        // Build preferences array from resource
        $preferences = [];

        if (null !== $data->preferredLanguage) {
            $preferences['preferredLanguage'] = $data->preferredLanguage;
        }

        if (null !== $data->preferredCurrency) {
            $preferences['preferredCurrency'] = $data->preferredCurrency;
        }

        if (null !== $data->newsletterSubscribed) {
            $preferences['newsletterSubscribed'] = $data->newsletterSubscribed;
        }

        if (null !== $data->marketingEmailsAllowed) {
            $preferences['marketingEmailsAllowed'] = $data->marketingEmailsAllowed;
        }

        if (null !== $data->smsNotificationsAllowed) {
            $preferences['smsNotificationsAllowed'] = $data->smsNotificationsAllowed;
        }

        if (null !== $data->orderNotificationsEnabled) {
            $preferences['orderNotificationsEnabled'] = $data->orderNotificationsEnabled;
        }

        if (null !== $data->promotionNotificationsEnabled) {
            $preferences['promotionNotificationsEnabled'] = $data->promotionNotificationsEnabled;
        }

        $command = new UpdatePreferencesCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            preferences: $preferences
        );

        $this->messageBus->dispatch($command);

        // Set customer ID for response
        $data->customerId = $customerId;

        return $data;
    }
}
