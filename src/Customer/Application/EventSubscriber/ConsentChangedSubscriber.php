<?php

declare(strict_types=1);

namespace App\Customer\Application\EventSubscriber;

use App\Customer\Domain\Event\CustomerConsentChanged;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Consent Changed Event Subscriber.
 *
 * Handles CustomerConsentChanged events for audit logging and downstream integrations.
 * Can be extended to trigger actions like:
 * - Unsubscribe from email marketing systems
 * - Remove from analytics tracking
 * - Notify third-party systems about consent withdrawal
 */
final readonly class ConsentChangedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CustomerConsentChanged::class => 'onConsentChanged',
        ];
    }

    public function onConsentChanged(CustomerConsentChanged $event): void
    {
        // Log consent change for audit trail
        $this->logger->info('Customer consent changed', [
            'customer_id' => $event->customerId()->toString(),
            'tenant_id' => $event->tenantId()->toString(),
            'consent_type' => $event->consentType()->value,
            'consent_label' => $event->consentType()->label(),
            'granted' => $event->granted(),
            'action' => $event->granted() ? 'granted' : 'withdrawn',
            'ip_address' => $event->ipAddress(),
            'occurred_at' => $event->occurredAt()->format('Y-m-d H:i:s'),
        ]);

        // Handle consent withdrawal actions
        if (!$event->granted()) {
            $this->handleConsentWithdrawal($event);
        }
    }

    /**
     * Handle specific actions when consent is withdrawn.
     */
    private function handleConsentWithdrawal(CustomerConsentChanged $event): void
    {
        // TODO: Integrate with external systems based on consent type
        // Examples:
        // - MARKETING_EMAIL: Unsubscribe from Mailchimp/SendGrid
        // - MARKETING_SMS: Remove from Twilio contact list
        // - ANALYTICS_TRACKING: Disable tracking cookies
        // - THIRD_PARTY_SHARING: Request data deletion from partners

        $this->logger->notice('Consent withdrawn - action required', [
            'customer_id' => $event->customerId()->toString(),
            'consent_type' => $event->consentType()->value,
            'message' => sprintf(
                'Customer withdrew %s consent. Manual review may be required for external system integration.',
                $event->consentType()->label()
            ),
        ]);
    }
}
