<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\ValueObject;

use App\Pricing\Domain\ValueObject\PromotionId;
use PHPUnit\Framework\TestCase;

final class PromotionIdTest extends TestCase
{
    public function testGenerateCreatesValidUlid(): void
    {
        $id = PromotionId::generate();

        $this->assertInstanceOf(PromotionId::class, $id);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id->toString());
    }

    public function testFromStringCreatesValidPromotionId(): void
    {
        $ulidString = (string) new \Symfony\Component\Uid\Ulid();
        $id = PromotionId::fromString($ulidString);

        $this->assertSame($ulidString, $id->toString());
    }

    public function testFromStringThrowsExceptionForInvalidUlid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PromotionId');

        PromotionId::fromString('invalid-ulid');
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $ulidString = (string) new \Symfony\Component\Uid\Ulid();
        $id1 = PromotionId::fromString($ulidString);
        $id2 = PromotionId::fromString($ulidString);

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentValues(): void
    {
        $id1 = PromotionId::generate();
        $id2 = PromotionId::generate();

        $this->assertFalse($id1->equals($id2));
    }

    public function testGenerateCreatesUniqueIds(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = PromotionId::generate()->toString();
        }

        $this->assertCount(100, array_unique($ids));
    }
}
