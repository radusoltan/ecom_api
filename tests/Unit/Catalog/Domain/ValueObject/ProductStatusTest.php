<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\ProductStatus;
use PHPUnit\Framework\TestCase;

final class ProductStatusTest extends TestCase
{
    public function testCreateDraftStatus(): void
    {
        $status = ProductStatus::draft();

        $this->assertSame('draft', $status->value());
        $this->assertTrue($status->isDraft());
        $this->assertFalse($status->isActive());
        $this->assertFalse($status->isInactive());
        $this->assertFalse($status->isDiscontinued());
    }

    public function testCreateActiveStatus(): void
    {
        $status = ProductStatus::active();

        $this->assertSame('active', $status->value());
        $this->assertTrue($status->isActive());
        $this->assertFalse($status->isDraft());
    }

    public function testCreateInactiveStatus(): void
    {
        $status = ProductStatus::inactive();

        $this->assertSame('inactive', $status->value());
        $this->assertTrue($status->isInactive());
    }

    public function testCreateDiscontinuedStatus(): void
    {
        $status = ProductStatus::discontinued();

        $this->assertSame('discontinued', $status->value());
        $this->assertTrue($status->isDiscontinued());
    }

    public function testFromStringCreatesStatus(): void
    {
        $status = ProductStatus::fromString('active');

        $this->assertTrue($status->isActive());
    }

    public function testFromStringNormalizesCase(): void
    {
        $status = ProductStatus::fromString('ACTIVE');

        $this->assertSame('active', $status->value());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $status = ProductStatus::fromString('  inactive  ');

        $this->assertSame('inactive', $status->value());
    }

    public function testRejectsInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid product status "invalid"');

        ProductStatus::fromString('invalid');
    }

    public function testDraftCanTransitionToActive(): void
    {
        $draft = ProductStatus::draft();
        $active = ProductStatus::active();

        $this->assertTrue($draft->canTransitionTo($active));
    }

    public function testDraftCannotTransitionToInactive(): void
    {
        $draft = ProductStatus::draft();
        $inactive = ProductStatus::inactive();

        $this->assertFalse($draft->canTransitionTo($inactive));
    }

    public function testActiveCanTransitionToInactive(): void
    {
        $active = ProductStatus::active();
        $inactive = ProductStatus::inactive();

        $this->assertTrue($active->canTransitionTo($inactive));
    }

    public function testInactiveCanTransitionToActive(): void
    {
        $inactive = ProductStatus::inactive();
        $active = ProductStatus::active();

        $this->assertTrue($inactive->canTransitionTo($active));
    }

    public function testAnyStatusCanTransitionToDiscontinued(): void
    {
        $discontinued = ProductStatus::discontinued();

        $this->assertTrue(ProductStatus::draft()->canTransitionTo($discontinued));
        $this->assertTrue(ProductStatus::active()->canTransitionTo($discontinued));
        $this->assertTrue(ProductStatus::inactive()->canTransitionTo($discontinued));
    }

    public function testDiscontinuedCannotTransitionToAnyStatus(): void
    {
        $discontinued = ProductStatus::discontinued();

        $this->assertFalse($discontinued->canTransitionTo(ProductStatus::draft()));
        $this->assertFalse($discontinued->canTransitionTo(ProductStatus::active()));
        $this->assertFalse($discontinued->canTransitionTo(ProductStatus::inactive()));
        $this->assertFalse($discontinued->canTransitionTo(ProductStatus::discontinued()));
    }

    public function testEqualsReturnsTrueForSameStatus(): void
    {
        $status1 = ProductStatus::active();
        $status2 = ProductStatus::active();

        $this->assertTrue($status1->equals($status2));
    }

    public function testEqualsReturnsFalseForDifferentStatus(): void
    {
        $active = ProductStatus::active();
        $inactive = ProductStatus::inactive();

        $this->assertFalse($active->equals($inactive));
    }

    public function testToStringReturnsValue(): void
    {
        $status = ProductStatus::active();

        $this->assertSame('active', (string) $status);
    }
}
