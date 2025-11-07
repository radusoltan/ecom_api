<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Payment Methods Provider - returns authenticated user's saved payment methods.
 *
 * Endpoint: GET /api/v1/profile/payment-methods
 * Returns: Array of payment methods
 *
 * TODO: Implement full payment method management:
 * - Integrate with Stripe Payment Methods API
 * - Store payment method tokens securely
 * - Add card brand, last4, expiry info
 * - Support multiple payment gateways (Stripe, PayPal, 2Checkout)
 * - Implement default payment method flag
 */
final readonly class PaymentMethodsProvider implements ProviderInterface
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

        if (null === $tenantId) {
            throw new \InvalidArgumentException('Tenant ID is required');
        }

        // TODO: Query payment methods from Stripe/PayPal/2Checkout
        // For now, return empty array (MVP)
        // Future implementation:
        // - $email = $user->getUserIdentifier();
        // - Find customer by email
        // - Fetch payment methods from payment gateway
        // - Return sanitized payment method data (no sensitive info)

        return [
            // Example structure for future implementation:
            // [
            //     'id' => 'pm_1234567890',
            //     'type' => 'card', // or 'paypal', 'bank_account'
            //     'brand' => 'visa', // visa, mastercard, amex, etc.
            //     'last4' => '4242',
            //     'expiryMonth' => 12,
            //     'expiryYear' => 2025,
            //     'isDefault' => true,
            //     'gateway' => 'stripe', // stripe, paypal, 2checkout
            // ]
        ];
    }
}
