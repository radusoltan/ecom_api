<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Domain\Model;

use App\Internationalization\Domain\Model\TranslationId;
use PHPUnit\Framework\TestCase;

final class TranslationIdTest extends TestCase
{
    public function testGenerateCreatesValidUuid(): void
    {
        $id = TranslationId::generate();
        self::assertNotEmpty($id->toString());
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id->toString()
        );
    }

    public function testFromStringAcceptsValidUuid(): void
    {
        $uuid = 'a1234567-89ab-4def-8123-456789abcdef';
        $id = TranslationId::fromString($uuid);
        self::assertSame($uuid, $id->toString());
    }

    public function testFromStringRejectsInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TranslationId::fromString('not-a-uuid');
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $uuid = 'a1234567-89ab-4def-8123-456789abcdef';
        $id1 = TranslationId::fromString($uuid);
        $id2 = TranslationId::fromString($uuid);
        self::assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $id1 = TranslationId::generate();
        $id2 = TranslationId::generate();
        self::assertFalse($id1->equals($id2));
    }

    public function testToStringMagicMethod(): void
    {
        $uuid = 'a1234567-89ab-4def-8123-456789abcdef';
        $id = TranslationId::fromString($uuid);
        self::assertSame($uuid, (string) $id);
    }
}
