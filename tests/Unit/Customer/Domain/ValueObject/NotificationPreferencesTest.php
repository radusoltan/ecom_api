<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\NotificationPreferences;
use PHPUnit\Framework\TestCase;

final class NotificationPreferencesTest extends TestCase
{
    public function testDefault(): void
    {
        $prefs = NotificationPreferences::default();

        self::assertTrue($prefs->orderUpdates());
        self::assertTrue($prefs->shippingUpdates());
        self::assertFalse($prefs->promotionalOffers());
        self::assertFalse($prefs->priceDropAlerts());
        self::assertFalse($prefs->backInStockAlerts());
        self::assertTrue($prefs->securityAlerts());
        self::assertFalse($prefs->newsletterWeekly());
        self::assertFalse($prefs->preferSms());
    }

    public function testCreate(): void
    {
        $prefs = NotificationPreferences::create(
            orderUpdates: true,
            shippingUpdates: false,
            promotionalOffers: true,
            priceDropAlerts: true,
            backInStockAlerts: true,
            securityAlerts: true,
            newsletterWeekly: true,
            preferSms: true
        );

        self::assertTrue($prefs->orderUpdates());
        self::assertFalse($prefs->shippingUpdates());
        self::assertTrue($prefs->promotionalOffers());
        self::assertTrue($prefs->priceDropAlerts());
        self::assertTrue($prefs->backInStockAlerts());
        self::assertTrue($prefs->securityAlerts());
        self::assertTrue($prefs->newsletterWeekly());
        self::assertTrue($prefs->preferSms());
    }

    public function testOrderUpdatesCannotBeDisabled(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order updates cannot be disabled');

        NotificationPreferences::create(
            orderUpdates: false,
            shippingUpdates: true,
            promotionalOffers: false,
            priceDropAlerts: false,
            backInStockAlerts: false,
            securityAlerts: true,
            newsletterWeekly: false,
            preferSms: false
        );
    }

    public function testSecurityAlertsCannotBeDisabled(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Security alerts cannot be disabled');

        NotificationPreferences::create(
            orderUpdates: true,
            shippingUpdates: true,
            promotionalOffers: false,
            priceDropAlerts: false,
            backInStockAlerts: false,
            securityAlerts: false,
            newsletterWeekly: false,
            preferSms: false
        );
    }

    public function testWithOrderUpdatesThrowsWhenDisabling(): void
    {
        $prefs = NotificationPreferences::default();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order updates cannot be disabled');

        $prefs->withOrderUpdates(false);
    }

    public function testWithSecurityAlertsThrowsWhenDisabling(): void
    {
        $prefs = NotificationPreferences::default();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Security alerts cannot be disabled');

        $prefs->withSecurityAlerts(false);
    }

    public function testWithShippingUpdates(): void
    {
        $original = NotificationPreferences::default();
        $updated = $original->withShippingUpdates(false);

        self::assertTrue($original->shippingUpdates());
        self::assertFalse($updated->shippingUpdates());
        self::assertNotSame($original, $updated);
    }

    public function testWithPromotionalOffers(): void
    {
        $original = NotificationPreferences::default();
        $updated = $original->withPromotionalOffers(true);

        self::assertFalse($original->promotionalOffers());
        self::assertTrue($updated->promotionalOffers());
        self::assertNotSame($original, $updated);
    }

    public function testWithPriceDropAlerts(): void
    {
        $original = NotificationPreferences::default();
        $updated = $original->withPriceDropAlerts(true);

        self::assertFalse($original->priceDropAlerts());
        self::assertTrue($updated->priceDropAlerts());
        self::assertNotSame($original, $updated);
    }

    public function testWithBackInStockAlerts(): void
    {
        $original = NotificationPreferences::default();
        $updated = $original->withBackInStockAlerts(true);

        self::assertFalse($original->backInStockAlerts());
        self::assertTrue($updated->backInStockAlerts());
        self::assertNotSame($original, $updated);
    }

    public function testWithNewsletterWeekly(): void
    {
        $original = NotificationPreferences::default();
        $updated = $original->withNewsletterWeekly(true);

        self::assertFalse($original->newsletterWeekly());
        self::assertTrue($updated->newsletterWeekly());
        self::assertNotSame($original, $updated);
    }

    public function testWithPreferSms(): void
    {
        $original = NotificationPreferences::default();
        $updated = $original->withPreferSms(true);

        self::assertFalse($original->preferSms());
        self::assertTrue($updated->preferSms());
        self::assertNotSame($original, $updated);
    }

    public function testFromArray(): void
    {
        $prefs = NotificationPreferences::fromArray([
            'orderUpdates' => true,
            'shippingUpdates' => false,
            'promotionalOffers' => true,
            'priceDropAlerts' => true,
            'backInStockAlerts' => true,
            'securityAlerts' => true,
            'newsletterWeekly' => true,
            'preferSms' => true,
        ]);

        self::assertTrue($prefs->orderUpdates());
        self::assertFalse($prefs->shippingUpdates());
        self::assertTrue($prefs->promotionalOffers());
        self::assertTrue($prefs->priceDropAlerts());
        self::assertTrue($prefs->backInStockAlerts());
        self::assertTrue($prefs->securityAlerts());
        self::assertTrue($prefs->newsletterWeekly());
        self::assertTrue($prefs->preferSms());
    }

    public function testFromArrayWithMissingKeys(): void
    {
        $prefs = NotificationPreferences::fromArray([
            'promotionalOffers' => true,
        ]);

        self::assertTrue($prefs->orderUpdates());
        self::assertTrue($prefs->shippingUpdates());
        self::assertTrue($prefs->promotionalOffers());
        self::assertTrue($prefs->securityAlerts());
    }

    public function testToArray(): void
    {
        $prefs = NotificationPreferences::create(
            orderUpdates: true,
            shippingUpdates: false,
            promotionalOffers: true,
            priceDropAlerts: false,
            backInStockAlerts: true,
            securityAlerts: true,
            newsletterWeekly: false,
            preferSms: true
        );

        $array = $prefs->toArray();

        self::assertEquals([
            'orderUpdates' => true,
            'shippingUpdates' => false,
            'promotionalOffers' => true,
            'priceDropAlerts' => false,
            'backInStockAlerts' => true,
            'securityAlerts' => true,
            'newsletterWeekly' => false,
            'preferSms' => true,
        ], $array);
    }

    public function testEquals(): void
    {
        $prefs1 = NotificationPreferences::create(
            true, false, true, false, true, true, false, true
        );
        $prefs2 = NotificationPreferences::create(
            true, false, true, false, true, true, false, true
        );

        self::assertTrue($prefs1->equals($prefs2));
    }

    public function testNotEquals(): void
    {
        $prefs1 = NotificationPreferences::create(
            true, false, true, false, true, true, false, true
        );
        $prefs2 = NotificationPreferences::create(
            true, true, true, false, true, true, false, true
        );

        self::assertFalse($prefs1->equals($prefs2));
    }
}
