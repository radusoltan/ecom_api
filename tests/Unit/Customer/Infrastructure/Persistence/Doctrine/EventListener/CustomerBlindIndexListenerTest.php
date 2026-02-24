<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Infrastructure\Persistence\Doctrine\EventListener;

use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use App\Customer\Infrastructure\Persistence\Doctrine\EventListener\CustomerBlindIndexListener;
use App\Shared\Infrastructure\Encryption\BlindIndexService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomerBlindIndexListener::class)]
final class CustomerBlindIndexListenerTest extends TestCase
{
    private const string BLIND_INDEX_HASH = 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1';

    #[Test]
    public function prePersistGeneratesEmailBlindIndexWhenMissing(): void
    {
        $blindIndexService = $this->createMock(BlindIndexService::class);
        $blindIndexService->expects(self::once())
            ->method('generate')
            ->with('test@example.com')
            ->willReturn(self::BLIND_INDEX_HASH);

        $entity = new CustomerEntity();
        $entity->setEmail('test@example.com');

        $listener = new CustomerBlindIndexListener($blindIndexService);
        $listener->prePersist($entity);

        self::assertSame(self::BLIND_INDEX_HASH, $entity->getEmailBlindIndex());
    }

    #[Test]
    public function prePersistDoesNotOverwriteExistingBlindIndex(): void
    {
        $blindIndexService = $this->createMock(BlindIndexService::class);
        $blindIndexService->expects(self::never())->method('generate');

        $entity = new CustomerEntity();
        $entity->setEmail('test@example.com');
        $entity->setEmailBlindIndex('existing-index');

        $listener = new CustomerBlindIndexListener($blindIndexService);
        $listener->prePersist($entity);

        self::assertSame('existing-index', $entity->getEmailBlindIndex());
    }

    #[Test]
    public function prePersistSkipsEmptyEmail(): void
    {
        $blindIndexService = $this->createMock(BlindIndexService::class);
        $blindIndexService->expects(self::never())->method('generate');

        $entity = new CustomerEntity();
        // email defaults to ''

        $listener = new CustomerBlindIndexListener($blindIndexService);
        $listener->prePersist($entity);

        self::assertNull($entity->getEmailBlindIndex());
    }

    #[Test]
    public function preUpdateGeneratesEmailBlindIndexWhenMissing(): void
    {
        $blindIndexService = $this->createMock(BlindIndexService::class);
        $blindIndexService->expects(self::once())
            ->method('generate')
            ->with('updated@example.com')
            ->willReturn(self::BLIND_INDEX_HASH);

        $entity = new CustomerEntity();
        $entity->setEmail('updated@example.com');

        $listener = new CustomerBlindIndexListener($blindIndexService);
        $listener->preUpdate($entity);

        self::assertSame(self::BLIND_INDEX_HASH, $entity->getEmailBlindIndex());
    }
}
