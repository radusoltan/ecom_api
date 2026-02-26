<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\ValueObject;

use App\Media\Domain\ValueObject\OwnerReference;
use App\Media\Domain\ValueObject\OwnerType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OwnerReference::class)]
final class OwnerReferenceTest extends TestCase
{
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------

    #[Test]
    public function itCreatesWithValidData(): void
    {
        $ref = OwnerReference::from($this->tenantId, OwnerType::PRODUCT, 'prod-abc');

        self::assertSame($this->tenantId, $ref->tenantId());
        self::assertSame(OwnerType::PRODUCT, $ref->type());
        self::assertSame('prod-abc', $ref->ownerId());
    }

    #[Test]
    public function itCreatesForCategoryOwner(): void
    {
        $ref = OwnerReference::from($this->tenantId, OwnerType::CATEGORY, 'cat-001');

        self::assertSame(OwnerType::CATEGORY, $ref->type());
        self::assertSame('cat-001', $ref->ownerId());
    }

    #[Test]
    public function itCreatesForUserOwner(): void
    {
        $ref = OwnerReference::from($this->tenantId, OwnerType::USER, 'usr-99');

        self::assertSame(OwnerType::USER, $ref->type());
        self::assertSame('usr-99', $ref->ownerId());
    }

    // -------------------------------------------------------------------
    // Rejection
    // -------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenOwnerIdIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner identifier cannot be empty.');

        OwnerReference::from($this->tenantId, OwnerType::PRODUCT, '');
    }

    #[Test]
    public function itThrowsWhenOwnerIdIsOnlyWhitespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner identifier cannot be empty.');

        OwnerReference::from($this->tenantId, OwnerType::PRODUCT, '   ');
    }
}
