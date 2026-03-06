<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Infrastructure\Persistence\Doctrine\Entity;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\CartItem;
use App\Cart\Domain\Model\CartItemId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Infrastructure\Persistence\Doctrine\Entity\CartEntity;
use App\Cart\Infrastructure\Persistence\Doctrine\Entity\CartItemEntity;
use App\Catalog\Domain\Model\ProductId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class CartItemEntityTest extends TestCase
{
    private CartEntity $cartEntity;

    protected function setUp(): void
    {
        $cart = Cart::create(CartId::generate(), TenantId::generate(), CustomerId::generate(), SessionId::generate());
        $this->cartEntity = CartEntity::fromDomainModel($cart);
    }

    public function testFromDomainModelRoundtrip(): void
    {
        $id = CartItemId::generate();
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(3);
        $unitPrice = Money::fromScalars(2999, 'USD');

        $item = CartItem::create($id, $productId, null, $quantity, $unitPrice);
        $tenantId = TenantId::generate()->toString();

        $entity = CartItemEntity::fromDomainModel($item, $this->cartEntity, $tenantId);

        self::assertSame($id->toString(), $entity->getId());
        self::assertSame($productId->toString(), $entity->getProductId());
        self::assertNull($entity->getVariantId());
        self::assertSame(3, $entity->getQuantity());
        self::assertSame(2999.0, $entity->getUnitPriceAmount());
        self::assertSame('USD', $entity->getUnitPriceCurrency());
        self::assertSame($tenantId, $entity->getTenantId());
        self::assertSame($this->cartEntity, $entity->getCart());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertSame($id->toString(), $restored->id()->toString());
        self::assertSame($productId->toString(), $restored->productId()->toString());
        self::assertNull($restored->variantId());
        self::assertSame(3, $restored->quantity()->toInt());
        self::assertSame(2999, $restored->unitPrice()->getAmount());
        self::assertSame('USD', $restored->unitPrice()->getCurrency()->getCurrencyCode());
    }

    public function testFromDomainModelWithVariantId(): void
    {
        $id = CartItemId::generate();
        $productId = ProductId::generate();
        $variantId = 'variant-00000000-0000-4000-8000-000000000001';
        $quantity = Quantity::fromInt(1);
        $unitPrice = Money::fromScalars(4999, 'EUR');

        $item = CartItem::create($id, $productId, $variantId, $quantity, $unitPrice);
        $entity = CartItemEntity::fromDomainModel($item, $this->cartEntity);

        self::assertSame($variantId, $entity->getVariantId());
        self::assertSame(1, $entity->getQuantity());
        self::assertSame(4999.0, $entity->getUnitPriceAmount());
        self::assertSame('EUR', $entity->getUnitPriceCurrency());

        $restored = $entity->toDomainModel();
        self::assertSame($variantId, $restored->variantId());
        self::assertSame(4999, $restored->unitPrice()->getAmount());
        self::assertSame('EUR', $restored->unitPrice()->getCurrency()->getCurrencyCode());
    }

    public function testUpdateFromDomainModel(): void
    {
        $id = CartItemId::generate();
        $productId = ProductId::generate();
        $item = CartItem::create($id, $productId, null, Quantity::fromInt(1), Money::fromScalars(1000, 'USD'));
        $entity = CartItemEntity::fromDomainModel($item, $this->cartEntity);

        self::assertSame(1, $entity->getQuantity());
        self::assertSame(1000.0, $entity->getUnitPriceAmount());

        $updatedItem = CartItem::create($id, $productId, null, Quantity::fromInt(5), Money::fromScalars(900, 'USD'));
        $entity->updateFromDomainModel($updatedItem);

        self::assertSame(5, $entity->getQuantity());
        self::assertSame(900.0, $entity->getUnitPriceAmount());
        // ID should not change
        self::assertSame($id->toString(), $entity->getId());
        self::assertSame($productId->toString(), $entity->getProductId());
    }

    public function testSetCartAndTenantId(): void
    {
        $id = CartItemId::generate();
        $item = CartItem::create($id, ProductId::generate(), null, Quantity::fromInt(2), Money::fromScalars(500, 'USD'));
        $entity = CartItemEntity::fromDomainModel($item, $this->cartEntity);

        $newTenantId = TenantId::generate()->toString();
        $entity->setTenantId($newTenantId);
        self::assertSame($newTenantId, $entity->getTenantId());

        $newCart = Cart::create(CartId::generate(), TenantId::generate(), null, SessionId::generate());
        $newCartEntity = CartEntity::fromDomainModel($newCart);
        $entity->setCart($newCartEntity);
        self::assertSame($newCartEntity, $entity->getCart());
    }

    public function testGetCartIdReturnsCartEntityId(): void
    {
        $id = CartItemId::generate();
        $item = CartItem::create($id, ProductId::generate(), null, Quantity::fromInt(1), Money::fromScalars(100, 'USD'));
        $entity = CartItemEntity::fromDomainModel($item, $this->cartEntity);

        self::assertSame($this->cartEntity->getId(), $entity->getCartId());
    }
}
