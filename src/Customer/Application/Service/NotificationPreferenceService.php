<?php

declare(strict_types=1);

namespace App\Customer\Application\Service;

use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Service to check customer notification preferences before sending notifications.
 *
 * Business Rules:
 * - Always respect customer's notification preferences
 * - Order updates and security alerts cannot be disabled (enforced at domain level)
 * - Promotional offers require marketing email consent
 * - SMS requires phone number to be set
 */
final readonly class NotificationPreferenceService
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {
    }

    /**
     * Check if an email notification should be sent to a customer.
     *
     * @param string $notificationType One of: 'order_update', 'shipping', 'promotional', 'price_drop', 'back_in_stock', 'security', 'newsletter'
     */
    public function shouldSendEmail(CustomerId $customerId, TenantId $tenantId, string $notificationType): bool
    {
        $customer = $this->customerRepository->findById($customerId, $tenantId);

        if (null === $customer) {
            return false;
        }

        $preferences = $customer->getNotificationPreferences();

        return match ($notificationType) {
            'order_update' => $preferences->orderUpdates(), // Always true (cannot be disabled)
            'shipping' => $preferences->shippingUpdates(),
            'promotional' => $preferences->promotionalOffers() && $customer->getConsents()->marketingEmail(),
            'price_drop' => $preferences->priceDropAlerts(),
            'back_in_stock' => $preferences->backInStockAlerts(),
            'security' => $preferences->securityAlerts(), // Always true (cannot be disabled)
            'newsletter' => $preferences->newsletterWeekly() && $customer->getConsents()->marketingEmail(),
            default => throw new \InvalidArgumentException(sprintf('Unknown notification type: %s', $notificationType)),
        };
    }

    /**
     * Check if an SMS notification should be sent to a customer.
     *
     * SMS is only sent if:
     * - Customer prefers SMS (preferSms = true)
     * - Customer has a phone number set
     * - The specific notification type is enabled
     *
     * @param string $notificationType One of: 'order_update', 'shipping', 'promotional', 'price_drop', 'back_in_stock', 'security', 'newsletter'
     */
    public function shouldSendSms(CustomerId $customerId, TenantId $tenantId, string $notificationType): bool
    {
        $customer = $this->customerRepository->findById($customerId, $tenantId);

        if (null === $customer) {
            return false;
        }

        // SMS requires phone number
        if (null === $customer->phoneNumber()) {
            return false;
        }

        $preferences = $customer->getNotificationPreferences();

        // Customer must prefer SMS
        if (!$preferences->preferSms()) {
            return false;
        }

        // Check if the specific notification type is enabled
        $typeEnabled = match ($notificationType) {
            'order_update' => $preferences->orderUpdates(), // Always true
            'shipping' => $preferences->shippingUpdates(),
            'promotional' => $preferences->promotionalOffers() && $customer->getConsents()->marketingSms(),
            'price_drop' => $preferences->priceDropAlerts(),
            'back_in_stock' => $preferences->backInStockAlerts(),
            'security' => $preferences->securityAlerts(), // Always true
            'newsletter' => $preferences->newsletterWeekly() && $customer->getConsents()->marketingSms(),
            default => throw new \InvalidArgumentException(sprintf('Unknown notification type: %s', $notificationType)),
        };

        return $typeEnabled;
    }

    /**
     * Get the preferred notification channel for a customer.
     *
     * @return string 'sms' or 'email'
     */
    public function getPreferredChannel(CustomerId $customerId, TenantId $tenantId): string
    {
        $customer = $this->customerRepository->findById($customerId, $tenantId);

        if (null === $customer) {
            return 'email'; // Default to email
        }

        $preferences = $customer->getNotificationPreferences();

        // SMS only if preferSms is true AND phone number is set
        if ($preferences->preferSms() && null !== $customer->phoneNumber()) {
            return 'sms';
        }

        return 'email';
    }

    /**
     * Check if an email notification should be sent to a customer (by email address).
     *
     * @param string $notificationType One of: 'order_update', 'shipping', 'promotional', 'price_drop', 'back_in_stock', 'security', 'newsletter'
     */
    public function shouldSendEmailByEmail(Email $email, TenantId $tenantId, string $notificationType): bool
    {
        $customer = $this->customerRepository->findByEmail($email, $tenantId);

        if (null === $customer) {
            // If customer not found, allow transactional emails (order_update, security) but not marketing
            return in_array($notificationType, ['order_update', 'security', 'shipping'], true);
        }

        $preferences = $customer->getNotificationPreferences();

        return match ($notificationType) {
            'order_update' => $preferences->orderUpdates(), // Always true (cannot be disabled)
            'shipping' => $preferences->shippingUpdates(),
            'promotional' => $preferences->promotionalOffers() && $customer->getConsents()->marketingEmail(),
            'price_drop' => $preferences->priceDropAlerts(),
            'back_in_stock' => $preferences->backInStockAlerts(),
            'security' => $preferences->securityAlerts(), // Always true (cannot be disabled)
            'newsletter' => $preferences->newsletterWeekly() && $customer->getConsents()->marketingEmail(),
            default => throw new \InvalidArgumentException(sprintf('Unknown notification type: %s', $notificationType)),
        };
    }

    /**
     * Check if an SMS notification should be sent to a customer (by email address).
     *
     * @param string $notificationType One of: 'order_update', 'shipping', 'promotional', 'price_drop', 'back_in_stock', 'security', 'newsletter'
     */
    public function shouldSendSmsByEmail(Email $email, TenantId $tenantId, string $notificationType): bool
    {
        $customer = $this->customerRepository->findByEmail($email, $tenantId);

        if (null === $customer) {
            return false;
        }

        // SMS requires phone number
        if (null === $customer->phoneNumber()) {
            return false;
        }

        $preferences = $customer->getNotificationPreferences();

        // Customer must prefer SMS
        if (!$preferences->preferSms()) {
            return false;
        }

        // Check if the specific notification type is enabled
        $typeEnabled = match ($notificationType) {
            'order_update' => $preferences->orderUpdates(), // Always true
            'shipping' => $preferences->shippingUpdates(),
            'promotional' => $preferences->promotionalOffers() && $customer->getConsents()->marketingSms(),
            'price_drop' => $preferences->priceDropAlerts(),
            'back_in_stock' => $preferences->backInStockAlerts(),
            'security' => $preferences->securityAlerts(), // Always true
            'newsletter' => $preferences->newsletterWeekly() && $customer->getConsents()->marketingSms(),
            default => throw new \InvalidArgumentException(sprintf('Unknown notification type: %s', $notificationType)),
        };

        return $typeEnabled;
    }
}
