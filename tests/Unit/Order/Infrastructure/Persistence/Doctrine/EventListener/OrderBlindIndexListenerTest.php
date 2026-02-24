<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Infrastructure\Persistence\Doctrine\EventListener;

use App\Order\Infrastructure\Persistence\Doctrine\Entity\OrderEntity;
use App\Order\Infrastructure\Persistence\Doctrine\EventListener\OrderBlindIndexListener;
use App\Shared\Infrastructure\Encryption\BlindIndexService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderBlindIndexListener::class)]
final class OrderBlindIndexListenerTest extends TestCase
{
    private const string BLIND_INDEX_HASH = 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1';

    #[Test]
    public function prePersistGeneratesCustomerEmailBlindIndexWhenMissing(): void
    {
        $blindIndexService = $this->createMock(BlindIndexService::class);
        $blindIndexService->expects(self::once())
            ->method('generate')
            ->with('customer@example.com')
            ->willReturn(self::BLIND_INDEX_HASH);

        $entity = $this->createOrderEntity('customer@example.com');

        $listener = new OrderBlindIndexListener($blindIndexService);
        $listener->prePersist($entity);

        self::assertSame(self::BLIND_INDEX_HASH, $entity->getCustomerEmailBlindIndex());
    }

    #[Test]
    public function prePersistDoesNotOverwriteExistingBlindIndex(): void
    {
        $blindIndexService = $this->createMock(BlindIndexService::class);
        $blindIndexService->expects(self::never())->method('generate');

        $entity = $this->createOrderEntity('customer@example.com');
        $entity->setCustomerEmailBlindIndex('existing-index');

        $listener = new OrderBlindIndexListener($blindIndexService);
        $listener->prePersist($entity);

        self::assertSame('existing-index', $entity->getCustomerEmailBlindIndex());
    }

    #[Test]
    public function prePersistSkipsEmptyEmail(): void
    {
        $blindIndexService = $this->createMock(BlindIndexService::class);
        $blindIndexService->expects(self::never())->method('generate');

        $entity = $this->createOrderEntity('');

        $listener = new OrderBlindIndexListener($blindIndexService);
        $listener->prePersist($entity);

        self::assertNull($entity->getCustomerEmailBlindIndex());
    }

    #[Test]
    public function preUpdateGeneratesCustomerEmailBlindIndexWhenMissing(): void
    {
        $blindIndexService = $this->createMock(BlindIndexService::class);
        $blindIndexService->expects(self::once())
            ->method('generate')
            ->with('updated@example.com')
            ->willReturn(self::BLIND_INDEX_HASH);

        $entity = $this->createOrderEntity('updated@example.com');

        $listener = new OrderBlindIndexListener($blindIndexService);
        $listener->preUpdate($entity);

        self::assertSame(self::BLIND_INDEX_HASH, $entity->getCustomerEmailBlindIndex());
    }

    private function createOrderEntity(string $customerEmail): OrderEntity
    {
        // OrderEntity doesn't have simple setters for all fields, use reflection
        $entity = new OrderEntity();
        $ref = new \ReflectionClass($entity);

        $emailProp = $ref->getProperty('customerEmail');
        $emailProp->setValue($entity, $customerEmail);

        $idProp = $ref->getProperty('id');
        $idProp->setValue($entity, 'test-order-id');

        $tenantProp = $ref->getProperty('tenantId');
        $tenantProp->setValue($entity, '00000000-0000-4000-8000-000000000001');

        return $entity;
    }
}
