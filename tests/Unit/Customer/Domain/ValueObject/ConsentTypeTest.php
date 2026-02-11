<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\ConsentType;
use PHPUnit\Framework\TestCase;

final class ConsentTypeTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = ConsentType::cases();

        self::assertCount(4, $cases);
        self::assertContains(ConsentType::MARKETING_EMAIL, $cases);
        self::assertContains(ConsentType::MARKETING_SMS, $cases);
        self::assertContains(ConsentType::THIRD_PARTY_SHARING, $cases);
        self::assertContains(ConsentType::ANALYTICS_TRACKING, $cases);
    }

    public function testLabel(): void
    {
        self::assertEquals('Marketing Emails', ConsentType::MARKETING_EMAIL->label());
        self::assertEquals('Marketing SMS', ConsentType::MARKETING_SMS->label());
        self::assertEquals('Third-Party Data Sharing', ConsentType::THIRD_PARTY_SHARING->label());
        self::assertEquals('Analytics Tracking', ConsentType::ANALYTICS_TRACKING->label());
    }

    public function testDescription(): void
    {
        self::assertEquals(
            'Receive promotional emails about products, offers, and news',
            ConsentType::MARKETING_EMAIL->description()
        );

        self::assertEquals(
            'Receive promotional SMS messages about special offers and updates',
            ConsentType::MARKETING_SMS->description()
        );

        self::assertEquals(
            'Allow sharing your data with trusted third-party partners for marketing purposes',
            ConsentType::THIRD_PARTY_SHARING->description()
        );

        self::assertEquals(
            'Allow tracking your behavior for analytics and improving user experience',
            ConsentType::ANALYTICS_TRACKING->description()
        );
    }

    public function testValue(): void
    {
        self::assertEquals('marketing_email', ConsentType::MARKETING_EMAIL->value);
        self::assertEquals('marketing_sms', ConsentType::MARKETING_SMS->value);
        self::assertEquals('third_party_sharing', ConsentType::THIRD_PARTY_SHARING->value);
        self::assertEquals('analytics_tracking', ConsentType::ANALYTICS_TRACKING->value);
    }

    public function testCanBeCreatedFromString(): void
    {
        self::assertSame(ConsentType::MARKETING_EMAIL, ConsentType::from('marketing_email'));
        self::assertSame(ConsentType::MARKETING_SMS, ConsentType::from('marketing_sms'));
        self::assertSame(ConsentType::THIRD_PARTY_SHARING, ConsentType::from('third_party_sharing'));
        self::assertSame(ConsentType::ANALYTICS_TRACKING, ConsentType::from('analytics_tracking'));
    }

    public function testThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);

        ConsentType::from('invalid_type');
    }
}
