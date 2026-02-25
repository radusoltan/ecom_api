<?php

declare(strict_types=1);

namespace App\Tests\Integration\Cart\Infrastructure\Repository;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Cart\Infrastructure\Persistence\Doctrine\Entity\CartEntity;
use App\Cart\Infrastructure\Persistence\Doctrine\Entity\CartItemEntity;
use App\Catalog\Domain\Model\ProductId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineCartRepositoryTest extends KernelTestCase
{
    use TenantTestTrait;

    private CartRepositoryInterface $repository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());

        $container = static::getContainer();
        $this->repository = $container->get(CartRepositoryInterface::class);

        $this->cleanupTestCarts();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestCarts();
        parent::tearDown();
    }

    private function cleanupTestCarts(): void
    {
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();

        $connection->executeStatement('DELETE FROM cart_items');
        $connection->executeStatement('DELETE FROM carts');
    }

    // ============================================
    // save() Tests
    // ============================================

    public function testSaveInsertsNewCart(): void
    {
        $cartId = CartId::generate();
        $sessionId = SessionId::generate();
        $cart = Cart::create($cartId, $this->tenantId, null, $sessionId);

        $this->repository->save($cart);

        $found = $this->repository->findById($cartId);

        $this->assertNotNull($found);
        $this->assertSame($cartId->toString(), $found->id()->toString());
        $this->assertSame($this->tenantId->toString(), $found->tenantId()->toString());
        $this->assertNull($found->customerId());
        $this->assertSame($sessionId->toString(), $found->sessionId()->toString());
        $this->assertSame('active', $found->status()->value());
    }

    public function testSaveInsertsCartWithItems(): void
    {
        $cart = Cart::create(CartId::generate(), $this->tenantId, null, SessionId::generate());
        $productId1 = ProductId::generate();
        $productId2 = ProductId::generate();

        $cart->addItem($productId1, null, Quantity::fromInt(2), Money::fromScalars(1999, 'EUR'));
        $cart->addItem($productId2, null, Quantity::fromInt(1), Money::fromScalars(500, 'EUR'));

        $this->repository->save($cart);

        $found = $this->repository->findById($cart->id());

        $this->assertNotNull($found);
        $this->assertCount(2, $found->items());
        $this->assertSame(3, $found->getItemCount());
    }

    public function testSaveUpdatesExistingCart(): void
    {
        $cart = Cart::create(CartId::generate(), $this->tenantId, null, SessionId::generate());
        $this->repository->save($cart);

        // Add item and save again
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(3), Money::fromScalars(2500, 'EUR'));
        $this->repository->save($cart);

        $found = $this->repository->findById($cart->id());

        $this->assertNotNull($found);
        $this->assertCount(1, $found->items());
        $this->assertSame(3, $found->getItemCount());
    }

    public function testSaveCartWithCustomerId(): void
    {
        $customerId = CustomerId::generate();
        $cart = Cart::create(CartId::generate(), $this->tenantId, $customerId, SessionId::generate());

        $this->repository->save($cart);

        $found = $this->repository->findById($cart->id());

        $this->assertNotNull($found);
        $this->assertNotNull($found->customerId());
        $this->assertSame($customerId->toString(), $found->customerId()->toString());
    }

    // ============================================
    // findById() Tests
    // ============================================

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findById(CartId::generate());

        $this->assertNull($result);
    }

    public function testFindByIdReturnsCartWithItems(): void
    {
        $cart = Cart::create(CartId::generate(), $this->tenantId, null, SessionId::generate());
        $productId = ProductId::generate();
        $cart->addItem($productId, null, Quantity::fromInt(5), Money::fromScalars(1000, 'EUR'));

        $this->repository->save($cart);

        $found = $this->repository->findById($cart->id());

        $this->assertNotNull($found);
        $this->assertCount(1, $found->items());
        $item = $found->items()[0];
        $this->assertSame($productId->toString(), $item->productId()->toString());
        $this->assertSame(5, $item->quantity()->toInt());
        $this->assertSame(1000, $item->unitPrice()->getAmount());
    }

    // ============================================
    // findByCustomerId() Tests
    // ============================================

    public function testFindByCustomerIdReturnsActiveCart(): void
    {
        $customerId = CustomerId::generate();
        $cart = Cart::create(CartId::generate(), $this->tenantId, $customerId, SessionId::generate());

        $this->repository->save($cart);

        $found = $this->repository->findByCustomerId($customerId, $this->tenantId);

        $this->assertNotNull($found);
        $this->assertSame($cart->id()->toString(), $found->id()->toString());
    }

    public function testFindByCustomerIdReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByCustomerId(CustomerId::generate(), $this->tenantId);

        $this->assertNull($result);
    }

    public function testFindByCustomerIdIgnoresExpiredCarts(): void
    {
        $customerId = CustomerId::generate();
        $cart = Cart::create(CartId::generate(), $this->tenantId, $customerId, SessionId::generate());
        $cart->markAsExpired();

        $this->repository->save($cart);

        $found = $this->repository->findByCustomerId($customerId, $this->tenantId);

        $this->assertNull($found);
    }

    // ============================================
    // findBySessionId() Tests
    // ============================================

    public function testFindBySessionIdReturnsActiveCart(): void
    {
        $sessionId = SessionId::generate();
        $cart = Cart::create(CartId::generate(), $this->tenantId, null, $sessionId);

        $this->repository->save($cart);

        $found = $this->repository->findBySessionId($sessionId, $this->tenantId);

        $this->assertNotNull($found);
        $this->assertSame($cart->id()->toString(), $found->id()->toString());
    }

    public function testFindBySessionIdReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findBySessionId(SessionId::generate(), $this->tenantId);

        $this->assertNull($result);
    }

    // ============================================
    // remove() Tests
    // ============================================

    public function testRemoveDeletesCart(): void
    {
        $cart = Cart::create(CartId::generate(), $this->tenantId, null, SessionId::generate());
        $this->repository->save($cart);

        $this->assertNotNull($this->repository->findById($cart->id()));

        $this->repository->remove($cart);

        $this->assertNull($this->repository->findById($cart->id()));
    }

    public function testRemoveDeletesCartWithItems(): void
    {
        $cart = Cart::create(CartId::generate(), $this->tenantId, null, SessionId::generate());
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(1), Money::fromScalars(1000, 'EUR'));
        $this->repository->save($cart);

        $this->repository->remove($cart);

        $this->assertNull($this->repository->findById($cart->id()));

        // Verify items are also removed (orphanRemoval)
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $items = $em->getRepository(CartItemEntity::class)->findAll();
        $this->assertCount(0, $items);
    }

    // ============================================
    // findExpired() Tests
    // ============================================

    public function testFindExpiredReturnsOldActiveCarts(): void
    {
        // Create a cart and manually backdate it in the DB
        $cart = Cart::create(CartId::generate(), $this->tenantId, null, SessionId::generate());
        $this->repository->save($cart);

        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();

        // Backdate the cart to 8 days ago
        $connection->executeStatement(
            'UPDATE carts SET updated_at = :updatedAt WHERE id = :id',
            [
                'updatedAt' => (new \DateTimeImmutable('-8 days'))->format('Y-m-d H:i:s'),
                'id' => $cart->id()->toString(),
            ]
        );

        $expired = $this->repository->findExpired(new \DateTimeImmutable('-7 days'));

        $this->assertCount(1, $expired);
        $this->assertSame($cart->id()->toString(), $expired[0]->id()->toString());
    }

    public function testFindExpiredExcludesRecentCarts(): void
    {
        $cart = Cart::create(CartId::generate(), $this->tenantId, null, SessionId::generate());
        $this->repository->save($cart);

        $expired = $this->repository->findExpired(new \DateTimeImmutable('-7 days'));

        $this->assertCount(0, $expired);
    }

    // ============================================
    // markAsConverted() Tests
    // ============================================

    public function testMarkAsConvertedUpdatesStatus(): void
    {
        $cart = Cart::create(CartId::generate(), $this->tenantId, null, SessionId::generate());
        $this->repository->save($cart);

        $this->repository->markAsConverted($cart->id());

        // Clear the entity manager cache to get fresh data
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();

        $found = $this->repository->findById($cart->id());

        $this->assertNotNull($found);
        $this->assertSame('converted', $found->status()->value());
    }

    // ============================================
    // markAbandonmentEmailSent() Tests
    // ============================================

    public function testMarkAbandonmentEmailSentUpdatesFlag(): void
    {
        $cart = Cart::create(CartId::generate(), $this->tenantId, null, SessionId::generate());
        $this->repository->save($cart);

        $this->repository->markAbandonmentEmailSent($cart->id());

        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $entity = $em->find(CartEntity::class, $cart->id()->toString());

        $this->assertNotNull($entity);
        $this->assertTrue($entity->isAbandonmentEmailSent());
    }

    // ============================================
    // Domain Event Dispatch Tests
    // ============================================

    public function testSaveClearsEventsAfterDispatch(): void
    {
        $cart = Cart::create(CartId::generate(), $this->tenantId, null, SessionId::generate());

        $this->repository->save($cart);

        // After save, events should have been dispatched and cleared
        $this->assertCount(0, $cart->popEvents());
    }
}
