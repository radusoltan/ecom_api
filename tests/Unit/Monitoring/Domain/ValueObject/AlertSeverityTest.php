<?php

declare(strict_types=1);

namespace App\Tests\Unit\Monitoring\Domain\ValueObject;

use App\Monitoring\Domain\ValueObject\AlertSeverity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlertSeverity::class)]
final class AlertSeverityTest extends TestCase
{
    #[Test]
    public function infoFactoryMethod(): void
    {
        $severity = AlertSeverity::info();

        self::assertSame('info', $severity->value());
        self::assertSame('info', (string) $severity);
    }

    #[Test]
    public function warningFactoryMethod(): void
    {
        $severity = AlertSeverity::warning();

        self::assertSame('warning', $severity->value());
    }

    #[Test]
    public function errorFactoryMethod(): void
    {
        $severity = AlertSeverity::error();

        self::assertSame('error', $severity->value());
    }

    #[Test]
    public function criticalFactoryMethod(): void
    {
        $severity = AlertSeverity::critical();

        self::assertSame('critical', $severity->value());
    }

    #[Test]
    #[TestWith(['info'])]
    #[TestWith(['warning'])]
    #[TestWith(['error'])]
    #[TestWith(['critical'])]
    public function fromStringCreatesValidSeverity(string $value): void
    {
        $severity = AlertSeverity::fromString($value);

        self::assertSame($value, $severity->value());
    }

    #[Test]
    public function fromStringWithInvalidValueThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid alert severity');

        AlertSeverity::fromString('unknown');
    }

    #[Test]
    public function equalsReturnsTrueForSameSeverity(): void
    {
        $a = AlertSeverity::warning();
        $b = AlertSeverity::warning();

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentSeverity(): void
    {
        $a = AlertSeverity::warning();
        $b = AlertSeverity::critical();

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function isHigherThanComparesCorrectly(): void
    {
        $info = AlertSeverity::info();
        $warning = AlertSeverity::warning();
        $error = AlertSeverity::error();
        $critical = AlertSeverity::critical();

        self::assertTrue($critical->isHigherThan($error));
        self::assertTrue($error->isHigherThan($warning));
        self::assertTrue($warning->isHigherThan($info));
        self::assertFalse($info->isHigherThan($warning));
        self::assertFalse($warning->isHigherThan($critical));
    }

    #[Test]
    public function isHigherThanReturnsFalseForEqualSeverity(): void
    {
        $a = AlertSeverity::error();
        $b = AlertSeverity::error();

        self::assertFalse($a->isHigherThan($b));
    }
}
