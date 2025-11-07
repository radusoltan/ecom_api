<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\SubscriptionInterval;
use PHPUnit\Framework\TestCase;

final class SubscriptionIntervalTest extends TestCase
{
    public function testAllIntervalCasesExist(): void
    {
        $this->assertSame('daily', SubscriptionInterval::DAILY->value);
        $this->assertSame('weekly', SubscriptionInterval::WEEKLY->value);
        $this->assertSame('monthly', SubscriptionInterval::MONTHLY->value);
        $this->assertSame('yearly', SubscriptionInterval::YEARLY->value);
    }

    public function testFromString(): void
    {
        $this->assertEquals(SubscriptionInterval::DAILY, SubscriptionInterval::fromString('daily'));
        $this->assertEquals(SubscriptionInterval::WEEKLY, SubscriptionInterval::fromString('weekly'));
        $this->assertEquals(SubscriptionInterval::MONTHLY, SubscriptionInterval::fromString('monthly'));
        $this->assertEquals(SubscriptionInterval::YEARLY, SubscriptionInterval::fromString('yearly'));
    }

    public function testFromStringWithInvalidValueThrows(): void
    {
        $this->expectException(\ValueError::class);

        SubscriptionInterval::fromString('invalid');
    }

    public function testIsDaily(): void
    {
        $this->assertTrue(SubscriptionInterval::DAILY->isDaily());
        $this->assertFalse(SubscriptionInterval::WEEKLY->isDaily());
    }

    public function testIsWeekly(): void
    {
        $this->assertTrue(SubscriptionInterval::WEEKLY->isWeekly());
        $this->assertFalse(SubscriptionInterval::DAILY->isWeekly());
    }

    public function testIsMonthly(): void
    {
        $this->assertTrue(SubscriptionInterval::MONTHLY->isMonthly());
        $this->assertFalse(SubscriptionInterval::YEARLY->isMonthly());
    }

    public function testIsYearly(): void
    {
        $this->assertTrue(SubscriptionInterval::YEARLY->isYearly());
        $this->assertFalse(SubscriptionInterval::MONTHLY->isYearly());
    }

    public function testLabel(): void
    {
        $this->assertEquals('Daily', SubscriptionInterval::DAILY->label());
        $this->assertEquals('Weekly', SubscriptionInterval::WEEKLY->label());
        $this->assertEquals('Monthly', SubscriptionInterval::MONTHLY->label());
        $this->assertEquals('Yearly', SubscriptionInterval::YEARLY->label());
    }

    public function testApproximateDays(): void
    {
        $this->assertEquals(1, SubscriptionInterval::DAILY->approximateDays());
        $this->assertEquals(7, SubscriptionInterval::WEEKLY->approximateDays());
        $this->assertEquals(30, SubscriptionInterval::MONTHLY->approximateDays());
        $this->assertEquals(365, SubscriptionInterval::YEARLY->approximateDays());
    }

    public function testValues(): void
    {
        $values = SubscriptionInterval::values();

        $this->assertCount(4, $values);
        $this->assertContains('daily', $values);
        $this->assertContains('weekly', $values);
        $this->assertContains('monthly', $values);
        $this->assertContains('yearly', $values);
    }
}
