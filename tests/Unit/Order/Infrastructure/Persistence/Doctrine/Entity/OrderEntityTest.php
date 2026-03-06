<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Infrastructure\Persistence\Doctrine\Entity;

use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\ValueObject\OrderProductId;
use App\Order\Infrastructure\Persistence\Doctrine\Entity\OrderEntity;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class OrderEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $line = OrderLine::create(
            OrderProductId::generate(),
            'Widget',
            2,
            Money::fromScalars(1500, 'USD'),
        );
        $shipping = Address::create('123 Main St', 'Berlin', 'BE', '10115', 'DE');
        $billing = Address::create('456 Bill St', 'Munich', 'BY', '80331', 'DE');

        $order = Order::place(
            $orderId, $tenantId, 'customer@example.com',
            [$line], $shipping, $billing,
            ['promo-1'], Money::fromScalars(500, 'USD'), 'SAVE10',
            Money::fromScalars(285, 'USD'), 'DE', 'tax-rule-1', 19.0, false, 'DE123456789',
        );

        $entity = OrderEntity::fromDomainModel($order);

        self::assertSame($orderId->toString(), $entity->getId());
        self::assertSame($tenantId->toString(), $entity->getTenantId());
        self::assertSame('customer@example.com', $entity->getCustomerEmail());
        self::assertSame('pending', $entity->getStatus());
        self::assertCount(1, $entity->getLines());
        self::assertSame('Widget', $entity->getLines()[0]['productName']);
        self::assertSame(2, $entity->getLines()[0]['quantity']);
        self::assertIsArray($entity->getShippingAddress());
        self::assertSame('Berlin', $entity->getShippingAddress()['city']);
        self::assertIsArray($entity->getBillingAddress());
        self::assertSame('Munich', $entity->getBillingAddress()['city']);
        self::assertSame(['promo-1'], $entity->getAppliedPromotions());
        self::assertEquals(500, $entity->getDiscountAmount());
        self::assertSame('USD', $entity->getDiscountCurrency());
        self::assertSame('SAVE10', $entity->getCouponCode());
        self::assertEquals(285, $entity->getTaxAmount());
        self::assertSame('USD', $entity->getTaxCurrency());
        self::assertSame('DE', $entity->getTaxJurisdiction());
        self::assertSame('tax-rule-1', $entity->getTaxRuleId());
        self::assertSame(19.0, $entity->getTaxRate());
        self::assertFalse($entity->isReverseCharge());
        self::assertSame('DE123456789', $entity->getVatNumber());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertTrue($restored->id()->equals($orderId));
        self::assertSame('customer@example.com', $restored->customerEmail());
        self::assertCount(1, $restored->lines());
    }

    public function testUpdateFromDomainModel(): void
    {
        $order = Order::place(
            OrderId::generate(), TenantId::generate(), 'test@example.com',
            [OrderLine::create(OrderProductId::generate(), 'Item', 1, Money::fromScalars(1000, 'USD'))],
            Address::create('1 St', 'City', 'ST', '00000', 'DE'),
            Address::create('2 St', 'City', 'ST', '00000', 'DE'),
        );

        $entity = OrderEntity::fromDomainModel($order);
        self::assertSame('pending', $entity->getStatus());

        $order->markAsPaid();
        $entity->updateFromDomainModel($order);

        self::assertSame('paid', $entity->getStatus());
    }

    public function testBlindIndex(): void
    {
        $order = Order::place(
            OrderId::generate(), TenantId::generate(), 'blind@example.com',
            [OrderLine::create(OrderProductId::generate(), 'Item', 1, Money::fromScalars(1000, 'USD'))],
            Address::create('1 St', 'City', 'ST', '00000', 'DE'),
            Address::create('2 St', 'City', 'ST', '00000', 'DE'),
        );

        $entity = OrderEntity::fromDomainModel($order);
        self::assertNull($entity->getCustomerEmailBlindIndex());

        $entity->setCustomerEmailBlindIndex('hashed-index');
        self::assertSame('hashed-index', $entity->getCustomerEmailBlindIndex());
    }
}
