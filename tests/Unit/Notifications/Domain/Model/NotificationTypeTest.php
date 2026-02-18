<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Domain\Model;

use App\Notifications\Domain\Model\NotificationType;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NotificationType enum.
 *
 * Tests all notification delivery channels.
 */
final class NotificationTypeTest extends TestCase
{
    public function testItHasEmailCase(): void
    {
        // Act
        $type = NotificationType::EMAIL;

        // Assert
        $this->assertSame('email', $type->value);
        $this->assertTrue($type->isEmail());
        $this->assertFalse($type->isSms());
        $this->assertFalse($type->isWebhook());
        $this->assertFalse($type->isPush());
    }

    public function testItHasSmsCase(): void
    {
        // Act
        $type = NotificationType::SMS;

        // Assert
        $this->assertSame('sms', $type->value);
        $this->assertTrue($type->isSms());
        $this->assertFalse($type->isEmail());
        $this->assertFalse($type->isWebhook());
        $this->assertFalse($type->isPush());
    }

    public function testItHasWebhookCase(): void
    {
        // Act
        $type = NotificationType::WEBHOOK;

        // Assert
        $this->assertSame('webhook', $type->value);
        $this->assertTrue($type->isWebhook());
        $this->assertFalse($type->isEmail());
        $this->assertFalse($type->isSms());
        $this->assertFalse($type->isPush());
    }

    public function testItHasPushCase(): void
    {
        // Act
        $type = NotificationType::PUSH;

        // Assert
        $this->assertSame('push', $type->value);
        $this->assertTrue($type->isPush());
        $this->assertFalse($type->isEmail());
        $this->assertFalse($type->isSms());
        $this->assertFalse($type->isWebhook());
    }

    public function testItSupportsAllCases(): void
    {
        // Arrange & Act
        $cases = NotificationType::cases();

        // Assert
        $this->assertCount(4, $cases);
        $this->assertContains(NotificationType::EMAIL, $cases);
        $this->assertContains(NotificationType::SMS, $cases);
        $this->assertContains(NotificationType::WEBHOOK, $cases);
        $this->assertContains(NotificationType::PUSH, $cases);
    }

    public function testItCreatesFromStringValue(): void
    {
        // Act
        $email = NotificationType::from('email');
        $sms = NotificationType::from('sms');
        $webhook = NotificationType::from('webhook');
        $push = NotificationType::from('push');

        // Assert
        $this->assertSame(NotificationType::EMAIL, $email);
        $this->assertSame(NotificationType::SMS, $sms);
        $this->assertSame(NotificationType::WEBHOOK, $webhook);
        $this->assertSame(NotificationType::PUSH, $push);
    }

    public function testItThrowsOnInvalidStringValue(): void
    {
        // Assert
        $this->expectException(\ValueError::class);

        // Act
        NotificationType::from('invalid');
    }

    public function testItReturnsNullForInvalidTryFrom(): void
    {
        // Act
        $result = NotificationType::tryFrom('invalid');

        // Assert
        $this->assertNull($result);
    }
}
