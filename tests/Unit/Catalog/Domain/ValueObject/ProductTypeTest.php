<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\ProductType;
use PHPUnit\Framework\TestCase;

final class ProductTypeTest extends TestCase
{
    public function testCreateSimpleType(): void
    {
        $type = ProductType::simple();

        $this->assertSame('simple', $type->value());
        $this->assertTrue($type->isSimple());
        $this->assertFalse($type->isConfigurable());
        $this->assertFalse($type->isBundle());
        $this->assertFalse($type->isVirtual());
        $this->assertFalse($type->isSubscription());
    }

    public function testCreateConfigurableType(): void
    {
        $type = ProductType::configurable();

        $this->assertSame('configurable', $type->value());
        $this->assertTrue($type->isConfigurable());
        $this->assertFalse($type->isSimple());
    }

    public function testCreateBundleType(): void
    {
        $type = ProductType::bundle();

        $this->assertSame('bundle', $type->value());
        $this->assertTrue($type->isBundle());
    }

    public function testCreateVirtualType(): void
    {
        $type = ProductType::virtual();

        $this->assertSame('virtual', $type->value());
        $this->assertTrue($type->isVirtual());
    }

    public function testCreateSubscriptionType(): void
    {
        $type = ProductType::subscription();

        $this->assertSame('subscription', $type->value());
        $this->assertTrue($type->isSubscription());
    }

    public function testFromStringCreatesType(): void
    {
        $type = ProductType::fromString('simple');

        $this->assertTrue($type->isSimple());
    }

    public function testFromStringNormalizesCase(): void
    {
        $type = ProductType::fromString('CONFIGURABLE');

        $this->assertSame('configurable', $type->value());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $type = ProductType::fromString('  bundle  ');

        $this->assertSame('bundle', $type->value());
    }

    public function testRejectsInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid product type "invalid"');

        ProductType::fromString('invalid');
    }

    public function testSimpleTypeRequiresShipping(): void
    {
        $type = ProductType::simple();

        $this->assertTrue($type->requiresShipping());
    }

    public function testVirtualTypeDoesNotRequireShipping(): void
    {
        $type = ProductType::virtual();

        $this->assertFalse($type->requiresShipping());
    }

    public function testSubscriptionTypeDoesNotRequireShipping(): void
    {
        $type = ProductType::subscription();

        $this->assertFalse($type->requiresShipping());
    }

    public function testConfigurableSupportsVariants(): void
    {
        $type = ProductType::configurable();

        $this->assertTrue($type->supportsVariants());
    }

    public function testSimpleDoesNotSupportVariants(): void
    {
        $type = ProductType::simple();

        $this->assertFalse($type->supportsVariants());
    }

    public function testBundleIsComposite(): void
    {
        $type = ProductType::bundle();

        $this->assertTrue($type->isComposite());
    }

    public function testSimpleIsNotComposite(): void
    {
        $type = ProductType::simple();

        $this->assertFalse($type->isComposite());
    }

    public function testEqualsReturnsTrueForSameType(): void
    {
        $type1 = ProductType::simple();
        $type2 = ProductType::simple();

        $this->assertTrue($type1->equals($type2));
    }

    public function testEqualsReturnsFalseForDifferentType(): void
    {
        $simple = ProductType::simple();
        $configurable = ProductType::configurable();

        $this->assertFalse($simple->equals($configurable));
    }

    public function testToStringReturnsValue(): void
    {
        $type = ProductType::configurable();

        $this->assertSame('configurable', (string) $type);
    }

    public function testAllTypesCanBeCreated(): void
    {
        $types = [
            ProductType::simple(),
            ProductType::configurable(),
            ProductType::bundle(),
            ProductType::virtual(),
            ProductType::subscription(),
        ];

        $this->assertCount(5, $types);

        foreach ($types as $type) {
            $this->assertInstanceOf(ProductType::class, $type);
        }
    }
}
