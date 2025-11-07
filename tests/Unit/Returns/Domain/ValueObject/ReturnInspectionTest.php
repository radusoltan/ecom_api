<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Domain\ValueObject;

use App\Returns\Domain\ValueObject\ReturnInspection;
use PHPUnit\Framework\TestCase;

final class ReturnInspectionTest extends TestCase
{
    // Creation Tests

    public function testCreateWithResellableItem(): void
    {
        $inspection = ReturnInspection::create(
            isResellable: true,
            notes: 'Item in perfect condition, original packaging intact'
        );

        $this->assertTrue($inspection->isResellable());
        $this->assertFalse($inspection->isDamaged());
        $this->assertEquals('Item in perfect condition, original packaging intact', $inspection->notes());
        $this->assertInstanceOf(\DateTimeImmutable::class, $inspection->inspectedAt());
    }

    public function testCreateWithDamagedItem(): void
    {
        $inspection = ReturnInspection::create(
            isResellable: false,
            notes: 'Item shows significant wear and tear, not suitable for resale'
        );

        $this->assertFalse($inspection->isResellable());
        $this->assertTrue($inspection->isDamaged());
        $this->assertEquals('Item shows significant wear and tear, not suitable for resale', $inspection->notes());
    }

    public function testCreateWithCustomTimestamp(): void
    {
        $inspectedAt = new \DateTimeImmutable('2025-01-15 14:30:00');

        $inspection = ReturnInspection::create(
            isResellable: true,
            notes: 'Inspection completed',
            inspectedAt: $inspectedAt
        );

        $this->assertEquals($inspectedAt, $inspection->inspectedAt());
    }

    public function testCreateTrimsWhitespace(): void
    {
        $inspection = ReturnInspection::create(
            isResellable: true,
            notes: '   Item condition is good   '
        );

        $this->assertEquals('Item condition is good', $inspection->notes());
    }

    // Validation Tests

    public function testCreateThrowsExceptionForEmptyNotes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Inspection notes cannot be empty.');

        ReturnInspection::create(
            isResellable: true,
            notes: ''
        );
    }

    public function testCreateThrowsExceptionForWhitespaceOnlyNotes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Inspection notes cannot be empty.');

        ReturnInspection::create(
            isResellable: true,
            notes: '   '
        );
    }

    public function testCreateThrowsExceptionForTooShortNotes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Inspection notes must be at least 10 characters/');

        ReturnInspection::create(
            isResellable: true,
            notes: 'Too short'  // 9 characters
        );
    }

    public function testCreateAcceptsMinimumLengthNotes(): void
    {
        $inspection = ReturnInspection::create(
            isResellable: true,
            notes: 'Good cond!'  // Exactly 10 characters
        );

        $this->assertEquals('Good cond!', $inspection->notes());
    }

    public function testCreateThrowsExceptionForTooLongNotes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Inspection notes cannot exceed 2000 characters/');

        $veryLongNotes = str_repeat('A', 2001);  // 2001 characters

        ReturnInspection::create(
            isResellable: true,
            notes: $veryLongNotes
        );
    }

    public function testCreateAcceptsMaximumLengthNotes(): void
    {
        $maxLengthNotes = str_repeat('A', 2000);  // Exactly 2000 characters

        $inspection = ReturnInspection::create(
            isResellable: true,
            notes: $maxLengthNotes
        );

        $this->assertEquals(2000, mb_strlen($inspection->notes()));
    }

    public function testCreateAcceptsMultibyteCharacters(): void
    {
        $inspection = ReturnInspection::create(
            isResellable: true,
            notes: '商品の状態は良好です。再販可能。日本語でのメモ。'
        );

        $this->assertStringContainsString('商品の状態は良好です', $inspection->notes());
    }

    // Time-based Tests

    public function testWasInspectedBetweenReturnsTrueWhenInRange(): void
    {
        $inspectedAt = new \DateTimeImmutable('2025-01-15 12:00:00');

        $inspection = ReturnInspection::create(
            isResellable: true,
            notes: 'Inspection at noon',
            inspectedAt: $inspectedAt
        );

        $since = new \DateTimeImmutable('2025-01-15 10:00:00');
        $until = new \DateTimeImmutable('2025-01-15 14:00:00');

        $this->assertTrue($inspection->wasInspectedBetween($since, $until));
    }

    public function testWasInspectedBetweenReturnsFalseWhenBeforeRange(): void
    {
        $inspectedAt = new \DateTimeImmutable('2025-01-15 09:00:00');

        $inspection = ReturnInspection::create(
            isResellable: true,
            notes: 'Early inspection',
            inspectedAt: $inspectedAt
        );

        $since = new \DateTimeImmutable('2025-01-15 10:00:00');
        $until = new \DateTimeImmutable('2025-01-15 14:00:00');

        $this->assertFalse($inspection->wasInspectedBetween($since, $until));
    }

    public function testWasInspectedBetweenReturnsFalseWhenAfterRange(): void
    {
        $inspectedAt = new \DateTimeImmutable('2025-01-15 15:00:00');

        $inspection = ReturnInspection::create(
            isResellable: true,
            notes: 'Late inspection',
            inspectedAt: $inspectedAt
        );

        $since = new \DateTimeImmutable('2025-01-15 10:00:00');
        $until = new \DateTimeImmutable('2025-01-15 14:00:00');

        $this->assertFalse($inspection->wasInspectedBetween($since, $until));
    }

    public function testGetAgeInDaysReturnsCorrectValue(): void
    {
        $inspectedAt = new \DateTimeImmutable('2025-01-01 10:00:00');

        $inspection = ReturnInspection::create(
            isResellable: true,
            notes: 'Inspected on New Year',
            inspectedAt: $inspectedAt
        );

        $now = new \DateTimeImmutable('2025-01-11 10:00:00');

        $this->assertEquals(10, $inspection->getAgeInDays($now));
    }

    public function testGetAgeInDaysReturnsZeroForSameDay(): void
    {
        $inspectedAt = new \DateTimeImmutable('2025-01-15 10:00:00');

        $inspection = ReturnInspection::create(
            isResellable: true,
            notes: 'Inspected today',
            inspectedAt: $inspectedAt
        );

        $now = new \DateTimeImmutable('2025-01-15 18:00:00');

        $this->assertEquals(0, $inspection->getAgeInDays($now));
    }

    // Equality Tests

    public function testEqualsReturnsTrueForSameValues(): void
    {
        $inspectedAt = new \DateTimeImmutable('2025-01-15 12:00:00');

        $inspection1 = ReturnInspection::create(
            isResellable: true,
            notes: 'Good condition',
            inspectedAt: $inspectedAt
        );

        $inspection2 = ReturnInspection::create(
            isResellable: true,
            notes: 'Good condition',
            inspectedAt: $inspectedAt
        );

        $this->assertTrue($inspection1->equals($inspection2));
    }

    public function testEqualsReturnsFalseForDifferentResellableFlag(): void
    {
        $inspectedAt = new \DateTimeImmutable('2025-01-15 12:00:00');

        $inspection1 = ReturnInspection::create(
            isResellable: true,
            notes: 'Good condition',
            inspectedAt: $inspectedAt
        );

        $inspection2 = ReturnInspection::create(
            isResellable: false,
            notes: 'Good condition',
            inspectedAt: $inspectedAt
        );

        $this->assertFalse($inspection1->equals($inspection2));
    }

    public function testEqualsReturnsFalseForDifferentNotes(): void
    {
        $inspectedAt = new \DateTimeImmutable('2025-01-15 12:00:00');

        $inspection1 = ReturnInspection::create(
            isResellable: true,
            notes: 'Good condition',
            inspectedAt: $inspectedAt
        );

        $inspection2 = ReturnInspection::create(
            isResellable: true,
            notes: 'Poor condition',
            inspectedAt: $inspectedAt
        );

        $this->assertFalse($inspection1->equals($inspection2));
    }

    public function testEqualsReturnsFalseForDifferentTimestamp(): void
    {
        $inspection1 = ReturnInspection::create(
            isResellable: true,
            notes: 'Good condition',
            inspectedAt: new \DateTimeImmutable('2025-01-15 12:00:00')
        );

        $inspection2 = ReturnInspection::create(
            isResellable: true,
            notes: 'Good condition',
            inspectedAt: new \DateTimeImmutable('2025-01-15 14:00:00')
        );

        $this->assertFalse($inspection1->equals($inspection2));
    }

    // Edge Case Tests

    public function testCreateWithSpecialCharactersInNotes(): void
    {
        $notes = 'Item has: <scratches>, "dents", & marks @ various points #1-5, émojis 🔧🔨';

        $inspection = ReturnInspection::create(
            isResellable: false,
            notes: $notes
        );

        $this->assertEquals($notes, $inspection->notes());
    }

    public function testCreateWithNewlinesInNotes(): void
    {
        $notes = "Line 1: Scratches on top\nLine 2: Dent on side\nLine 3: Overall poor condition";

        $inspection = ReturnInspection::create(
            isResellable: false,
            notes: $notes
        );

        $this->assertStringContainsString("\n", $inspection->notes());
    }
}
