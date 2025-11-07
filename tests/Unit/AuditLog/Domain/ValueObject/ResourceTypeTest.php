<?php

declare(strict_types=1);

namespace App\Tests\Unit\AuditLog\Domain\ValueObject;

use App\AuditLog\Domain\ValueObject\ResourceType;
use PHPUnit\Framework\TestCase;

final class ResourceTypeTest extends TestCase
{
    public function testFromStringCreatesValidResourceType(): void
    {
        $resourceType = ResourceType::fromString('user');

        $this->assertEquals('user', $resourceType->toString());
    }

    public function testFromStringThrowsExceptionForInvalidResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid resource type: invalid_resource');

        ResourceType::fromString('invalid_resource');
    }

    public function testUserFactoryMethod(): void
    {
        $resourceType = ResourceType::user();

        $this->assertEquals('user', $resourceType->toString());
    }

    public function testProductFactoryMethod(): void
    {
        $resourceType = ResourceType::product();

        $this->assertEquals('product', $resourceType->toString());
    }

    public function testCategoryFactoryMethod(): void
    {
        $resourceType = ResourceType::category();

        $this->assertEquals('category', $resourceType->toString());
    }

    public function testOrderFactoryMethod(): void
    {
        $resourceType = ResourceType::order();

        $this->assertEquals('order', $resourceType->toString());
    }

    public function testCustomerFactoryMethod(): void
    {
        $resourceType = ResourceType::customer();

        $this->assertEquals('customer', $resourceType->toString());
    }

    public function testPaymentFactoryMethod(): void
    {
        $resourceType = ResourceType::payment();

        $this->assertEquals('payment', $resourceType->toString());
    }

    public function testTenantFactoryMethod(): void
    {
        $resourceType = ResourceType::tenant();

        $this->assertEquals('tenant', $resourceType->toString());
    }

    public function testWarehouseFactoryMethod(): void
    {
        $resourceType = ResourceType::warehouse();

        $this->assertEquals('warehouse', $resourceType->toString());
    }

    public function testInventoryFactoryMethod(): void
    {
        $resourceType = ResourceType::inventory();

        $this->assertEquals('inventory', $resourceType->toString());
    }

    public function testPriceListFactoryMethod(): void
    {
        $resourceType = ResourceType::priceList();

        $this->assertEquals('price_list', $resourceType->toString());
    }

    public function testPromotionFactoryMethod(): void
    {
        $resourceType = ResourceType::promotion();

        $this->assertEquals('promotion', $resourceType->toString());
    }

    public function testCartFactoryMethod(): void
    {
        $resourceType = ResourceType::cart();

        $this->assertEquals('cart', $resourceType->toString());
    }

    public function testReviewFactoryMethod(): void
    {
        $resourceType = ResourceType::review();

        $this->assertEquals('review', $resourceType->toString());
    }

    public function testMediaFactoryMethod(): void
    {
        $resourceType = ResourceType::media();

        $this->assertEquals('media', $resourceType->toString());
    }

    public function testTaxFactoryMethod(): void
    {
        $resourceType = ResourceType::tax();

        $this->assertEquals('tax', $resourceType->toString());
    }

    public function testReturnFactoryMethod(): void
    {
        $resourceType = ResourceType::return();

        $this->assertEquals('return', $resourceType->toString());
    }

    public function testSettingsFactoryMethod(): void
    {
        $resourceType = ResourceType::settings();

        $this->assertEquals('settings', $resourceType->toString());
    }

    public function testEquals(): void
    {
        $resourceType1 = ResourceType::user();
        $resourceType2 = ResourceType::user();
        $resourceType3 = ResourceType::product();

        $this->assertTrue($resourceType1->equals($resourceType2));
        $this->assertFalse($resourceType1->equals($resourceType3));
    }

    public function testAllValidResourcesCanBeCreated(): void
    {
        $validResources = [
            'user', 'product', 'category', 'order', 'customer', 'payment',
            'tenant', 'warehouse', 'inventory', 'price_list', 'promotion',
            'cart', 'review', 'media', 'tax', 'return', 'settings',
        ];

        foreach ($validResources as $resource) {
            $resourceType = ResourceType::fromString($resource);
            $this->assertEquals($resource, $resourceType->toString());
        }
    }
}
