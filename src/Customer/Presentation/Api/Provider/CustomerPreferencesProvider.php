<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\Query\GetCustomerPreferences\GetCustomerPreferencesQuery;
use App\Customer\Presentation\Api\Resource\CustomerPreferencesResource;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Customer Preferences Provider.
 *
 * Provides customer preferences for API GET requests.
 *
 * @implements ProviderInterface<CustomerPreferencesResource>
 */
final class CustomerPreferencesProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly RequestStack $requestStack
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomerPreferencesResource
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

        $query = new GetCustomerPreferencesQuery(
            customerId: $customerId,
            tenantId: $tenantId
        );

        /** @var \App\Customer\Application\DTO\CustomerPreferencesDTO $dto */
        $dto = $this->handle($query);

        // Map DTO to Resource
        $resource = new CustomerPreferencesResource();
        $resource->customerId = $customerId;
        $resource->preferredLanguage = $dto->preferredLanguage;
        $resource->preferredCurrency = $dto->preferredCurrency;
        $resource->newsletterSubscribed = $dto->newsletterSubscribed;
        $resource->marketingEmailsAllowed = $dto->marketingEmailsAllowed;
        $resource->smsNotificationsAllowed = $dto->smsNotificationsAllowed;
        $resource->orderNotificationsEnabled = $dto->orderNotificationsEnabled;
        $resource->promotionNotificationsEnabled = $dto->promotionNotificationsEnabled;

        return $resource;
    }
}
