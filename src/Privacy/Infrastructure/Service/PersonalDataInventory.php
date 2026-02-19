<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Service;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Port\PersonalDataContributorInterface;
use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use App\Privacy\Domain\Service\PersonalDataInventoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Personal Data Inventory Service Implementation.
 *
 * Coordinates data export and deletion checks across bounded contexts
 * via the PersonalDataContributorInterface (no direct repository coupling).
 */
final readonly class PersonalDataInventory implements PersonalDataInventoryInterface
{
    /**
     * @param iterable<PersonalDataContributorInterface> $contributors
     */
    public function __construct(
        private ConsentRepositoryInterface $consentRepository,
        private iterable $contributors,
        private LoggerInterface $logger,
    ) {
    }

    public function exportCustomerData(array $subjectContext): array
    {
        $customerId = $subjectContext['customerId'] ?? null;

        if (null === $customerId) {
            return ['error' => 'Customer ID is required'];
        }

        // Collect data from all contributing contexts
        $contextData = [];
        foreach ($this->contributors as $contributor) {
            $data = $contributor->collectPersonalData($subjectContext);

            if ([] !== $data) {
                $contextData[$contributor->contextName()] = $data;

                // If customer contributor returned email, enrich context for other contributors
                if ('customer' === $contributor->contextName() && isset($data['email'])) {
                    $subjectContext['email'] = $data['email'];
                }
            }
        }

        // Export consents from Privacy context (local data)
        $consents = $this->consentRepository->findByCustomerId(
            CustomerId::fromString($customerId)
        );
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
            'exportDate' => (new \DateTimeImmutable())->format('c'),
            'dataCategories' => array_keys($this->getDataCategories()),
            'retentionPolicies' => $this->getRetentionPoliciesForCustomer(),
            'format' => 'application/json',
            'version' => '1.0',
        ];

        return array_merge($contextData, [
            'consents' => $consentsData,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @deprecated Anonymization is now handled via the event-driven flow:
     *             Privacy emits DataErasureRequested → Customer anonymizes data.
     */
    public function anonymizeCustomerData(array $subjectContext): void
    {
        $this->logger->warning('PersonalDataInventory::anonymizeCustomerData() is deprecated. Anonymization is triggered via DataErasureRequested events.', [
            'customerId' => $subjectContext['customerId'] ?? 'unknown',
        ]);
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

    public function canDeleteCustomerData(array $subjectContext): bool
    {
        foreach ($this->contributors as $contributor) {
            if (!$contributor->canDeleteData($subjectContext)) {
                return false;
            }
        }

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
