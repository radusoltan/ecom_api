<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Addresses Provider - returns authenticated user's saved addresses.
 *
 * Endpoint: GET /api/v1/profile/addresses
 * Returns: Array of addresses
 *
 * TODO: Implement full address management:
 * - Create CustomerAddress entity
 * - Create Address aggregate root
 * - Implement address CRUD operations
 * - Add default address flag
 */
final readonly class AddressesProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Get authenticated user
        $user = $this->security->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        // Get tenant ID from header
        $request = $this->requestStack->getCurrentRequest();
        $tenantId = $request?->headers->get('X-Tenant-ID');

        if ($tenantId === null) {
            throw new \InvalidArgumentException('Tenant ID is required');
        }

        // TODO: Query addresses from database
        // For now, return empty array (MVP)
        // Future implementation:
        // - $email = $user->getUserIdentifier();
        // - Find customer by email
        // - Return customer's addresses

        return [
            // Example structure for future implementation:
            // [
            //     'id' => '123e4567-e89b-12d3-a456-426614174000',
            //     'street' => '123 Main St',
            //     'city' => 'San Francisco',
            //     'state' => 'CA',
            //     'postalCode' => '94102',
            //     'country' => 'US',
            //     'type' => 'shipping', // or 'billing'
            //     'label' => 'Home',
            //     'isDefault' => true,
            // ]
        ];
    }
}
