<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Service;

use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use App\Privacy\Domain\Service\PersonalDataInventoryInterface;
use App\User\Domain\Repository\UserRepositoryInterface;
use DateTimeImmutable;

/**
 * Personal Data Inventory Service Implementation
 *
 * Coordinates data export and anonymization across bounded contexts
 */
final readonly class PersonalDataInventory implements PersonalDataInventoryInterface
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private ?UserRepositoryInterface $userRepository,
        private OrderRepositoryInterface $orderRepository,
        private ConsentRepositoryInterface $consentRepository
    ) {
    }

    public function exportCustomerData(CustomerId $customerId): array
    {
        $customer = $this->customerRepository->findById($customerId);

        if ($customer === null) {
            return [
                'error' => 'Customer not found',
                'customerId' => $customerId->toString(),
            ];
        }

        // Export customer profile
        $customerData = [
            'id' => $customer->id()->toString(),
            'email' => $customer->email()->value(),
            'firstName' => $customer->firstName(),
            'lastName' => $customer->lastName(),
            'phoneNumber' => $customer->phoneNumber(),
            'segment' => $customer->segment()->value(),
            'loyaltyPoints' => $customer->loyaltyPoints(),
            'isActive' => $customer->isActive(),
            'createdAt' => $customer->createdAt()->format('c'),
            'updatedAt' => $customer->updatedAt()->format('c'),
        ];

        // Export user authentication data (if exists)
        $userData = null;
        if ($this->userRepository !== null) {
            $user = $this->userRepository->findByEmail($customer->email());
            if ($user !== null) {
                $userData = [
                    'id' => $user->id()->toString(),
                    'email' => $user->email()->value(),
                    'username' => $user->username()->toString(),
                    'roles' => $user->rolesAsStrings(),
                    'createdAt' => $user->createdAt()->format('c'),
                    // Password is hashed and not exportable (security)
                ];
            }
        }

        // Export orders
        $orders = $this->orderRepository->findByCustomerId($customerId);
        $ordersData = array_map(function ($order) {
            return [
                'id' => $order->id()->toString(),
                'status' => $order->status()->value(),
                'customerEmail' => $order->customerEmail(),
                'shippingAddress' => [
                    'street' => $order->shippingAddress()->street(),
                    'city' => $order->shippingAddress()->city(),
                    'state' => $order->shippingAddress()->state(),
                    'postalCode' => $order->shippingAddress()->postalCode(),
                    'country' => $order->shippingAddress()->country(),
                ],
                'billingAddress' => [
                    'street' => $order->billingAddress()->street(),
                    'city' => $order->billingAddress()->city(),
                    'state' => $order->billingAddress()->state(),
                    'postalCode' => $order->billingAddress()->postalCode(),
                    'country' => $order->billingAddress()->country(),
                ],
                'total' => $order->total()->toString(),
                'createdAt' => $order->createdAt()->format('c'),
                'updatedAt' => $order->updatedAt()->format('c'),
            ];
        }, $orders);

        // Export consents
        $consents = $this->consentRepository->findByCustomerId($customerId);
        $consentsData = array_map(function ($consent) {
            return [
                'id' => $consent->id()->toString(),
                'purpose' => $consent->purpose()->value(),
                'isGranted' => $consent->isGranted(),
                'grantedAt' => $consent->grantedAt()?->format('c'),
                'withdrawnAt' => $consent->withdrawnAt()?->format('c'),
                'consentVersion' => $consent->consentVersion(),
                'ipAddress' => $consent->ipAddress(),
            ];
        }, $consents);

        // Metadata
        $metadata = [
            'exportDate' => (new DateTimeImmutable())->format('c'),
            'dataCategories' => array_keys($this->getDataCategories()),
            'retentionPolicies' => $this->getRetentionPoliciesForCustomer(),
            'format' => 'application/json',
            'version' => '1.0',
        ];

        return [
            'customer' => $customerData,
            'user' => $userData,
            'orders' => $ordersData,
            'consents' => $consentsData,
            'metadata' => $metadata,
        ];
    }

    public function anonymizeCustomerData(CustomerId $customerId): void
    {
        $customer = $this->customerRepository->findById($customerId);

        if ($customer === null) {
            throw new \InvalidArgumentException(
                sprintf('Customer not found: %s', $customerId->toString())
            );
        }

        // Anonymize customer profile
        // Note: This would typically be done through a command that updates the customer
        // For now, we document the strategy

        // Strategy:
        // 1. Customer: Replace name with "DELETED_USER", email with "deleted-{uuid}@anonymized.local"
        // 2. User: Delete authentication record entirely
        // 3. Orders: Keep for 7 years (legal requirement), but anonymize customerEmail
        // 4. Consents: Withdraw all active consents, keep history for proof

        // This would be implemented by dispatching commands to each context
        // Example: UpdateCustomerCommand with anonymized data
        // Example: DeleteUserCommand
        // Example: AnonymizeOrderCustomerCommand

        throw new \RuntimeException(
            'Anonymization must be implemented by dispatching commands to each bounded context. See PersonalDataInventory::anonymizeCustomerData() for strategy.'
        );
    }

    public function getDataCategories(): array
    {
        return [
            'identity' => [
                'category' => 'Identity Data',
                'description' => 'Name, email, phone number',
                'retention_period' => '3 years after last activity',
                'lawful_basis' => 'Contract performance (Article 6.1.b)',
                'contexts' => ['Customer', 'User'],
            ],
            'contact' => [
                'category' => 'Contact Data',
                'description' => 'Shipping and billing addresses',
                'retention_period' => '7 years (tax compliance)',
                'lawful_basis' => 'Legal obligation (Article 6.1.c)',
                'contexts' => ['Order'],
            ],
            'transaction' => [
                'category' => 'Transaction Data',
                'description' => 'Order history, payment details (tokenized)',
                'retention_period' => '7 years (accounting)',
                'lawful_basis' => 'Legal obligation (Article 6.1.c)',
                'contexts' => ['Order', 'Payment'],
            ],
            'behavioral' => [
                'category' => 'Behavioral Data',
                'description' => 'Loyalty points, customer segment',
                'retention_period' => '3 years after last activity',
                'lawful_basis' => 'Legitimate interest (Article 6.1.f)',
                'contexts' => ['Customer'],
            ],
            'consent' => [
                'category' => 'Consent Records',
                'description' => 'Consent history, IP addresses, user agent',
                'retention_period' => '3 years after withdrawal',
                'lawful_basis' => 'Legal obligation (Article 6.1.c) - proof of compliance',
                'contexts' => ['Privacy'],
            ],
            'authentication' => [
                'category' => 'Authentication Data',
                'description' => 'Username, hashed password',
                'retention_period' => 'Until account deletion',
                'lawful_basis' => 'Contract performance (Article 6.1.b)',
                'contexts' => ['User'],
            ],
        ];
    }

    public function getProcessingPurposes(): array
    {
        return [
            'contract_performance' => [
                'purpose' => 'Contract Performance',
                'description' => 'Processing orders, delivering products',
                'lawful_basis' => 'Article 6.1.b - Contract performance',
                'requires_consent' => false,
            ],
            'legal_obligation' => [
                'purpose' => 'Legal Obligation',
                'description' => 'Tax compliance, accounting, anti-fraud',
                'lawful_basis' => 'Article 6.1.c - Legal obligation',
                'requires_consent' => false,
            ],
            'marketing' => [
                'purpose' => 'Marketing',
                'description' => 'Email/SMS marketing communications',
                'lawful_basis' => 'Article 6.1.a - Consent',
                'requires_consent' => true,
            ],
            'analytics' => [
                'purpose' => 'Analytics',
                'description' => 'Usage analytics, behavior tracking',
                'lawful_basis' => 'Article 6.1.a - Consent',
                'requires_consent' => true,
            ],
            'profiling' => [
                'purpose' => 'Profiling',
                'description' => 'Personalization, product recommendations',
                'lawful_basis' => 'Article 6.1.a - Consent',
                'requires_consent' => true,
            ],
            'legitimate_interest' => [
                'purpose' => 'Legitimate Interest',
                'description' => 'Fraud prevention, security, customer support',
                'lawful_basis' => 'Article 6.1.f - Legitimate interest',
                'requires_consent' => false,
            ],
        ];
    }

    public function canDeleteCustomerData(CustomerId $customerId): bool
    {
        // Check if there are active orders (not delivered/cancelled)
        $orders = $this->orderRepository->findByCustomerId($customerId);

        foreach ($orders as $order) {
            if (!$order->status()->isFinal()) {
                // Has pending/processing orders
                return false;
            }

            // Check if order is within retention period (7 years)
            $retentionDate = $order->createdAt()->modify('+7 years');
            if ($retentionDate > new DateTimeImmutable()) {
                // Order is within legal retention period
                return false;
            }
        }

        // Can safely anonymize if no blocking conditions
        return true;
    }

    private function getRetentionPoliciesForCustomer(): array
    {
        return [
            'customer_profile' => '3 years after last activity',
            'order_history' => '7 years (tax/accounting compliance)',
            'consent_records' => '3 years after withdrawal (proof of compliance)',
            'authentication' => 'Until account deletion',
        ];
    }
}
