<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\CustomerPreferences;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomerPreferences::class)]
final class CustomerPreferencesTest extends TestCase
{
    // ---------------------------------------------------------------
    // create() – defaults
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesWithDefaultValues(): void
    {
        $prefs = CustomerPreferences::create();

        self::assertSame('en', $prefs->preferredLanguage());
        self::assertSame('EUR', $prefs->preferredCurrency());
        self::assertFalse($prefs->newsletterSubscribed());
        self::assertFalse($prefs->marketingEmailsAllowed());
        self::assertFalse($prefs->smsNotificationsAllowed());
        self::assertTrue($prefs->orderNotificationsEnabled());
        self::assertFalse($prefs->promotionNotificationsEnabled());
    }

    #[Test]
    public function itCreatesWithExplicitValues(): void
    {
        $prefs = CustomerPreferences::create(
            preferredLanguage: 'fr',
            preferredCurrency: 'USD',
            newsletterSubscribed: true,
            marketingEmailsAllowed: true,
            smsNotificationsAllowed: true,
            orderNotificationsEnabled: false,
            promotionNotificationsEnabled: true,
        );

        self::assertSame('fr', $prefs->preferredLanguage());
        self::assertSame('USD', $prefs->preferredCurrency());
        self::assertTrue($prefs->newsletterSubscribed());
        self::assertTrue($prefs->marketingEmailsAllowed());
        self::assertTrue($prefs->smsNotificationsAllowed());
        self::assertFalse($prefs->orderNotificationsEnabled());
        self::assertTrue($prefs->promotionNotificationsEnabled());
    }

    // ---------------------------------------------------------------
    // fromArray() – reconstitution
    // ---------------------------------------------------------------

    #[Test]
    public function itReconstitutsFromFullArray(): void
    {
        $data = [
            'preferredLanguage' => 'de',
            'preferredCurrency' => 'RON',
            'newsletterSubscribed' => true,
            'marketingEmailsAllowed' => true,
            'smsNotificationsAllowed' => false,
            'orderNotificationsEnabled' => true,
            'promotionNotificationsEnabled' => false,
        ];

        $prefs = CustomerPreferences::fromArray($data);

        self::assertSame('de', $prefs->preferredLanguage());
        self::assertSame('RON', $prefs->preferredCurrency());
        self::assertTrue($prefs->newsletterSubscribed());
    }

    #[Test]
    public function itUsesDefaultsForMissingArrayKeys(): void
    {
        $prefs = CustomerPreferences::fromArray([]);

        self::assertSame('en', $prefs->preferredLanguage());
        self::assertSame('EUR', $prefs->preferredCurrency());
    }

    // ---------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsForUnsupportedLanguage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid language: "es"');

        CustomerPreferences::create(preferredLanguage: 'es');
    }

    #[Test]
    public function itThrowsForUnsupportedCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid currency: "GBP"');

        CustomerPreferences::create(preferredCurrency: 'GBP');
    }

    // ---------------------------------------------------------------
    // withXxx() wither methods – immutability
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsNewInstanceWithUpdatedLanguage(): void
    {
        $original = CustomerPreferences::create();
        $updated = $original->withLanguage('ro');

        self::assertNotSame($original, $updated);
        self::assertSame('en', $original->preferredLanguage());
        self::assertSame('ro', $updated->preferredLanguage());
    }

    #[Test]
    public function itThrowsWithLanguageForUnsupportedValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid language: "it"');

        CustomerPreferences::create()->withLanguage('it');
    }

    #[Test]
    public function itReturnsNewInstanceWithUpdatedCurrency(): void
    {
        $original = CustomerPreferences::create();
        $updated = $original->withCurrency('usd');  // lowercase should be normalized

        self::assertSame('USD', $updated->preferredCurrency());
        self::assertSame('EUR', $original->preferredCurrency());
    }

    #[Test]
    public function itThrowsWithCurrencyForUnsupportedValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CustomerPreferences::create()->withCurrency('JPY');
    }

    #[Test]
    public function itReturnsNewInstanceWithNewsletterSubscription(): void
    {
        $original = CustomerPreferences::create(newsletterSubscribed: false);
        $updated = $original->withNewsletterSubscription(true);

        self::assertTrue($updated->newsletterSubscribed());
        self::assertFalse($original->newsletterSubscribed());
    }

    #[Test]
    public function itReturnsNewInstanceWithMarketingEmails(): void
    {
        $original = CustomerPreferences::create(marketingEmailsAllowed: false);
        $updated = $original->withMarketingEmails(true);

        self::assertTrue($updated->marketingEmailsAllowed());
        self::assertFalse($original->marketingEmailsAllowed());
    }

    #[Test]
    public function itReturnsNewInstanceWithSmsNotifications(): void
    {
        $original = CustomerPreferences::create(smsNotificationsAllowed: false);
        $updated = $original->withSmsNotifications(true);

        self::assertTrue($updated->smsNotificationsAllowed());
    }

    #[Test]
    public function itReturnsNewInstanceWithOrderNotifications(): void
    {
        $original = CustomerPreferences::create(orderNotificationsEnabled: true);
        $updated = $original->withOrderNotifications(false);

        self::assertFalse($updated->orderNotificationsEnabled());
        self::assertTrue($original->orderNotificationsEnabled());
    }

    #[Test]
    public function itReturnsNewInstanceWithPromotionNotifications(): void
    {
        $original = CustomerPreferences::create(promotionNotificationsEnabled: false);
        $updated = $original->withPromotionNotifications(true);

        self::assertTrue($updated->promotionNotificationsEnabled());
    }

    // ---------------------------------------------------------------
    // equals()
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsTrueForIdenticalPreferences(): void
    {
        $a = CustomerPreferences::create(preferredLanguage: 'fr', preferredCurrency: 'EUR');
        $b = CustomerPreferences::create(preferredLanguage: 'fr', preferredCurrency: 'EUR');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itReturnsFalseForDifferentLanguage(): void
    {
        $a = CustomerPreferences::create(preferredLanguage: 'en');
        $b = CustomerPreferences::create(preferredLanguage: 'fr');

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itReturnsFalseForDifferentCurrency(): void
    {
        $a = CustomerPreferences::create(preferredCurrency: 'EUR');
        $b = CustomerPreferences::create(preferredCurrency: 'USD');

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itReturnsFalseForDifferentBooleanFlag(): void
    {
        $a = CustomerPreferences::create(newsletterSubscribed: true);
        $b = CustomerPreferences::create(newsletterSubscribed: false);

        self::assertFalse($a->equals($b));
    }

    // ---------------------------------------------------------------
    // toArray() round-trip
    // ---------------------------------------------------------------

    #[Test]
    public function itRoundTripsThroughToArrayAndFromArray(): void
    {
        $original = CustomerPreferences::create(
            preferredLanguage: 'de',
            preferredCurrency: 'RON',
            newsletterSubscribed: true,
            marketingEmailsAllowed: false,
            smsNotificationsAllowed: true,
            orderNotificationsEnabled: false,
            promotionNotificationsEnabled: true,
        );

        $restored = CustomerPreferences::fromArray($original->toArray());

        self::assertTrue($original->equals($restored));
    }

    #[Test]
    public function itReturnsAllKeysInToArray(): void
    {
        $prefs = CustomerPreferences::create();
        $array = $prefs->toArray();

        self::assertArrayHasKey('preferredLanguage', $array);
        self::assertArrayHasKey('preferredCurrency', $array);
        self::assertArrayHasKey('newsletterSubscribed', $array);
        self::assertArrayHasKey('marketingEmailsAllowed', $array);
        self::assertArrayHasKey('smsNotificationsAllowed', $array);
        self::assertArrayHasKey('orderNotificationsEnabled', $array);
        self::assertArrayHasKey('promotionNotificationsEnabled', $array);
    }
}
