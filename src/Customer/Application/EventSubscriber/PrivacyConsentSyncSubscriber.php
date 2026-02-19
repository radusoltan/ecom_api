<?php

declare(strict_types=1);

namespace App\Customer\Application\EventSubscriber;

use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Event\ConsentGranted;
use App\Privacy\Domain\Event\ConsentWithdrawn;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Syncs Privacy context consent decisions into Customer context consent flags.
 *
 * Privacy context is the GDPR source of truth for consent.
 * Customer context maintains simplified boolean flags as a read-model cache.
 */
final readonly class PrivacyConsentSyncSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConsentGranted::class => 'onConsentGranted',
            ConsentWithdrawn::class => 'onConsentWithdrawn',
        ];
    }

    public function onConsentGranted(ConsentGranted $event): void
    {
        $this->syncConsent($event->customerId, $event->tenantId, $event->purpose->value(), true);
    }

    public function onConsentWithdrawn(ConsentWithdrawn $event): void
    {
        $this->syncConsent($event->customerId, $event->tenantId, $event->purpose->value(), false);
    }

    private function syncConsent(CustomerId $customerId, TenantId $tenantId, string $purpose, bool $granted): void
    {
        $consentTypes = $this->mapPurposeToConsentTypes($purpose);

        if ([] === $consentTypes) {
            $this->logger->debug('Privacy consent purpose has no Customer equivalent', [
                'purpose' => $purpose,
                'customerId' => $customerId->toString(),
            ]);

            return;
        }

        $customer = $this->customerRepository->findById($customerId, $tenantId);

        if (null === $customer) {
            $this->logger->warning('Customer not found for consent sync', [
                'customerId' => $customerId->toString(),
                'tenantId' => $tenantId->toString(),
            ]);

            return;
        }

        foreach ($consentTypes as $consentType) {
            $customer->updateConsent($consentType, $granted);
        }

        $this->customerRepository->save($customer);

        $this->logger->info('Customer consent synced from Privacy context', [
            'customerId' => $customerId->toString(),
            'purpose' => $purpose,
            'granted' => $granted,
            'mappedTypes' => array_map(fn (ConsentType $t) => $t->value, $consentTypes),
        ]);
    }

    /**
     * @return ConsentType[]
     */
    private function mapPurposeToConsentTypes(string $purpose): array
    {
        return match ($purpose) {
            'marketing' => [ConsentType::MARKETING_EMAIL, ConsentType::MARKETING_SMS],
            'analytics' => [ConsentType::ANALYTICS_TRACKING],
            'third_party_sharing' => [ConsentType::THIRD_PARTY_SHARING],
            default => [],
        };
    }
}
