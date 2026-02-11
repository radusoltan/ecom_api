<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\CustomerConsent;
use PHPUnit\Framework\TestCase;

final class CustomerConsentTest extends TestCase
{
    public function testCreate(): void
    {
        $consent = CustomerConsent::create(
            marketingEmail: true,
            marketingSms: false,
            thirdPartySharing: true,
            analyticsTracking: false
        );

        self::assertTrue($consent->marketingEmail());
        self::assertFalse($consent->marketingSms());
        self::assertTrue($consent->thirdPartySharing());
        self::assertFalse($consent->analyticsTracking());
    }

    public function testDefaultReturnsAllFalse(): void
    {
        $consent = CustomerConsent::default();

        self::assertFalse($consent->marketingEmail());
        self::assertFalse($consent->marketingSms());
        self::assertFalse($consent->thirdPartySharing());
        self::assertFalse($consent->analyticsTracking());
    }

    public function testFromArray(): void
    {
        $consent = CustomerConsent::fromArray([
            'marketingEmail' => true,
            'marketingSms' => true,
            'thirdPartySharing' => false,
            'analyticsTracking' => true,
        ]);

        self::assertTrue($consent->marketingEmail());
        self::assertTrue($consent->marketingSms());
        self::assertFalse($consent->thirdPartySharing());
        self::assertTrue($consent->analyticsTracking());
    }

    public function testFromArrayWithMissingKeys(): void
    {
        $consent = CustomerConsent::fromArray([
            'marketingEmail' => true,
        ]);

        self::assertTrue($consent->marketingEmail());
        self::assertFalse($consent->marketingSms());
        self::assertFalse($consent->thirdPartySharing());
        self::assertFalse($consent->analyticsTracking());
    }

    public function testWithMarketingEmail(): void
    {
        $original = CustomerConsent::default();
        $updated = $original->withMarketingEmail(true);

        self::assertFalse($original->marketingEmail());
        self::assertTrue($updated->marketingEmail());
        self::assertFalse($updated->marketingSms());
        self::assertFalse($updated->thirdPartySharing());
        self::assertFalse($updated->analyticsTracking());
    }

    public function testWithMarketingSms(): void
    {
        $original = CustomerConsent::default();
        $updated = $original->withMarketingSms(true);

        self::assertFalse($original->marketingSms());
        self::assertTrue($updated->marketingSms());
        self::assertFalse($updated->marketingEmail());
    }

    public function testWithThirdPartySharing(): void
    {
        $original = CustomerConsent::default();
        $updated = $original->withThirdPartySharing(true);

        self::assertFalse($original->thirdPartySharing());
        self::assertTrue($updated->thirdPartySharing());
        self::assertFalse($updated->marketingEmail());
    }

    public function testWithAnalyticsTracking(): void
    {
        $original = CustomerConsent::default();
        $updated = $original->withAnalyticsTracking(true);

        self::assertFalse($original->analyticsTracking());
        self::assertTrue($updated->analyticsTracking());
        self::assertFalse($updated->marketingEmail());
    }

    public function testToArray(): void
    {
        $consent = CustomerConsent::create(
            marketingEmail: true,
            marketingSms: false,
            thirdPartySharing: true,
            analyticsTracking: false
        );

        $array = $consent->toArray();

        self::assertEquals([
            'marketingEmail' => true,
            'marketingSms' => false,
            'thirdPartySharing' => true,
            'analyticsTracking' => false,
        ], $array);
    }

    public function testEquals(): void
    {
        $consent1 = CustomerConsent::create(true, false, true, false);
        $consent2 = CustomerConsent::create(true, false, true, false);

        self::assertTrue($consent1->equals($consent2));
    }

    public function testNotEquals(): void
    {
        $consent1 = CustomerConsent::create(true, false, true, false);
        $consent2 = CustomerConsent::create(false, false, true, false);

        self::assertFalse($consent1->equals($consent2));
    }

    public function testNotEqualsWhenOneFieldDiffers(): void
    {
        $consent1 = CustomerConsent::create(true, true, true, true);
        $consent2 = CustomerConsent::create(true, true, true, false);

        self::assertFalse($consent1->equals($consent2));
    }

    public function testImmutability(): void
    {
        $original = CustomerConsent::default();
        $updated = $original->withMarketingEmail(true);

        self::assertNotSame($original, $updated);
        self::assertFalse($original->marketingEmail());
        self::assertTrue($updated->marketingEmail());
    }

    public function testImmutabilityWithMultipleUpdates(): void
    {
        $original = CustomerConsent::default();
        $step1 = $original->withMarketingEmail(true);
        $step2 = $step1->withMarketingSms(true);
        $step3 = $step2->withThirdPartySharing(true);

        // Each step creates a new instance
        self::assertNotSame($original, $step1);
        self::assertNotSame($step1, $step2);
        self::assertNotSame($step2, $step3);

        // Original remains unchanged
        self::assertFalse($original->marketingEmail());
        self::assertFalse($original->marketingSms());
        self::assertFalse($original->thirdPartySharing());

        // Final has all updates
        self::assertTrue($step3->marketingEmail());
        self::assertTrue($step3->marketingSms());
        self::assertTrue($step3->thirdPartySharing());
    }
}
