<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\Model;

use App\Inventory\Domain\Model\WarehouseName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WarehouseName::class)]
final class WarehouseNameEdgeCasesTest extends TestCase
{
    // ---------------------------------------------------------------
    // Boundary lengths
    // ---------------------------------------------------------------

    #[Test]
    public function itAcceptsTwoCharacterName(): void
    {
        $name = WarehouseName::fromString('AB');

        self::assertSame('AB', $name->value());
    }

    #[Test]
    public function itAccepts100CharacterName(): void
    {
        $long = str_repeat('X', 100);
        $name = WarehouseName::fromString($long);

        self::assertSame($long, $name->value());
    }

    #[Test]
    public function itThrowsForOneCharacterName(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('must be at least 2 characters long');

        WarehouseName::fromString('X');
    }

    #[Test]
    public function itThrowsFor101CharacterName(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('cannot exceed 100 characters');

        WarehouseName::fromString(str_repeat('Y', 101));
    }

    // ---------------------------------------------------------------
    // Whitespace trim
    // ---------------------------------------------------------------

    #[Test]
    public function itTrimsLeadingAndTrailingWhitespace(): void
    {
        $name = WarehouseName::fromString('   Central Warehouse   ');

        self::assertSame('Central Warehouse', $name->value());
    }

    #[Test]
    public function itThrowsWhenOnlyWhitespaceAfterTrim(): void
    {
        self::expectException(\InvalidArgumentException::class);

        // Two spaces trim to empty → too short
        WarehouseName::fromString('  ');
    }

    // ---------------------------------------------------------------
    // __toString
    // ---------------------------------------------------------------

    #[Test]
    public function itToStringReturnsSameAsValue(): void
    {
        $name = WarehouseName::fromString('Distribution Hub');

        self::assertSame('Distribution Hub', (string) $name);
    }

    // ---------------------------------------------------------------
    // equals
    // ---------------------------------------------------------------

    #[Test]
    public function itEqualsIdenticalName(): void
    {
        $a = WarehouseName::fromString('Main Depot');
        $b = WarehouseName::fromString('Main Depot');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itDoesNotEqualDifferentName(): void
    {
        $a = WarehouseName::fromString('Main Depot');
        $b = WarehouseName::fromString('Secondary Depot');

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itIsCaseSensitiveInEquals(): void
    {
        $a = WarehouseName::fromString('Depot A');
        $b = WarehouseName::fromString('depot a');

        self::assertFalse($a->equals($b));
    }

    // ---------------------------------------------------------------
    // Multibyte / special chars
    // ---------------------------------------------------------------

    #[Test]
    public function itCountsMultibyteCharactersCorrectlyForMinLength(): void
    {
        // Two multibyte chars — should pass the 2-char minimum
        $name = WarehouseName::fromString('ÄÖ');

        self::assertSame('ÄÖ', $name->value());
    }

    #[Test]
    public function itAcceptsNumbers(): void
    {
        $name = WarehouseName::fromString('WH2025');

        self::assertSame('WH2025', $name->value());
    }
}
