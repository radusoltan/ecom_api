<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Infrastructure\Persistence\Doctrine\Entity;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Infrastructure\Persistence\Doctrine\Entity\CartEntity;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class CartEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $cartId = CartId::generate();
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();
        $sessionId = SessionId::generate();

        $cart = Cart::create($cartId, $tenantId, $customerId, $sessionId);
        $entity = CartEntity::fromDomainModel($cart);

        self::assertSame($cartId->toString(), $entity->getId());
        self::assertSame($tenantId->toString(), $entity->getTenantId());
        self::assertSame($customerId->toString(), $entity->getCustomerId());
        self::assertSame($sessionId->toString(), $entity->getSessionId());
        self::assertSame('active', $entity->getStatus());
        self::assertCount(0, $entity->getItems());
        self::assertFalse($entity->isAbandonmentEmailSent());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertTrue($restored->id()->equals($cartId));
    }

    public function testUpdateStatusAndAbandonment(): void
    {
        $cart = Cart::create(CartId::generate(), TenantId::generate(), null, SessionId::generate());
        $entity = CartEntity::fromDomainModel($cart);

        $entity->updateStatus('abandoned');
        self::assertSame('abandoned', $entity->getStatus());

        $entity->markAbandonmentEmailSent();
        self::assertTrue($entity->isAbandonmentEmailSent());
    }
}
