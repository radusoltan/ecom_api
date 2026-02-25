<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\ValueObject;

use App\Pricing\Domain\ValueObject\PromotionId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PromotionIdTest extends TestCase
{
    public function testGenerateCreatesValidUuid(): void
    {
        $id = PromotionId::generate();

        $this->assertInstanceOf(PromotionId::class, $id);
        $this->assertTrue(Uuid::isValid($id->toString()));
    }

    public function testFromStringCreatesValidPromotionId(): void
    {
        $uuidString = (string) Uuid::v7();
        $id = PromotionId::fromString($uuidString);

        $this->assertSame($uuidString, $id->toString());
    }

    public function testFromStringThrowsExceptionForInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PromotionId');

        PromotionId::fromString('invalid-uuid');
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $uuidString = (string) Uuid::v7();
        $id1 = PromotionId::fromString($uuidString);
        $id2 = PromotionId::fromString($uuidString);

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
        for ($i = 0; $i < 100; ++$i) {
            $ids[] = PromotionId::generate()->toString();
        }

        $this->assertCount(100, array_unique($ids));
    }
}
