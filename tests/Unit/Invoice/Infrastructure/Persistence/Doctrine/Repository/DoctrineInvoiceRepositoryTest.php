<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Infrastructure\Persistence\Doctrine\Repository;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Invoice\Domain\Model\InvoiceStatus;
use App\Invoice\Infrastructure\Persistence\Doctrine\Entity\InvoiceEntity;
use App\Invoice\Infrastructure\Persistence\Doctrine\Repository\DoctrineInvoiceRepository;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[CoversClass(DoctrineInvoiceRepository::class)]
final class DoctrineInvoiceRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private DoctrineInvoiceRepository $repository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->repository = new DoctrineInvoiceRepository($this->entityManager, $this->eventDispatcher);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createQueryMock(mixed $result, bool $collection = false): Query&MockObject
    {
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->getMock();

        $query->method('setParameter')->willReturnSelf();

        if ($collection) {
            $query->method('getResult')->willReturn($result);
        } else {
            $query->method('getOneOrNullResult')->willReturn($result);
        }

        return $query;
    }

    private function createInvoiceEntityMock(): InvoiceEntity&MockObject
    {
        $entity = $this->createMock(InvoiceEntity::class);
        $domainModel = $this->createMock(Invoice::class);
        $entity->method('toDomainModel')->willReturn($domainModel);

        return $entity;
    }

    private function createInvoiceMock(array $events = []): Invoice&MockObject
    {
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('id')->willReturn(InvoiceId::generate());
        $invoice->method('tenantId')->willReturn($this->tenantId);
        $invoice->method('orderId')->willReturn(OrderId::fromString('00000000-0000-4000-8000-000000000002'));
        $invoice->method('customerId')->willReturn(CustomerId::fromString('00000000-0000-4000-8000-000000000010'));
        $invoice->method('status')->willReturn(InvoiceStatus::DRAFT);
        $invoice->method('createdAt')->willReturn(new \DateTimeImmutable());
        $invoice->method('updatedAt')->willReturn(new \DateTimeImmutable());
        $invoice->method('lines')->willReturn([]);
        $invoice->method('popEvents')->willReturn($events);

        return $invoice;
    }

    // -----------------------------------------------------------------------
    // save() - new entity
    // -----------------------------------------------------------------------

    #[Test]
    public function saveCreatesNewEntityWhenNotFound(): void
    {
        $invoice = $this->createInvoiceMock();
        $this->entityManager->method('find')->willReturn(null);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $this->repository->save($invoice);
    }

    // -----------------------------------------------------------------------
    // save() - existing entity
    // -----------------------------------------------------------------------

    #[Test]
    public function saveUpdatesExistingEntity(): void
    {
        $invoice = $this->createInvoiceMock();
        $existingEntity = $this->createMock(InvoiceEntity::class);
        $existingEntity->expects(self::once())->method('updateFromDomainModel')->with($invoice);

        $this->entityManager->method('find')->willReturn($existingEntity);
        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $this->repository->save($invoice);
    }

    // -----------------------------------------------------------------------
    // save() - dispatches events
    // -----------------------------------------------------------------------

    #[Test]
    public function saveDispatchesDomainEvents(): void
    {
        $event = new \stdClass();
        $invoice = $this->createInvoiceMock([$event]);
        $this->entityManager->method('find')->willReturn(null);

        $this->eventDispatcher->expects(self::once())->method('dispatch')->with($event);

        $this->repository->save($invoice);
    }

    // -----------------------------------------------------------------------
    // findById()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByIdReturnsNullWhenNotFound(): void
    {
        $query = $this->createQueryMock(null);
        $this->entityManager->method('createQuery')->willReturn($query);

        $result = $this->repository->findById(InvoiceId::generate());

        self::assertNull($result);
    }

    #[Test]
    public function findByIdReturnsDomainModelWhenFound(): void
    {
        $entity = $this->createInvoiceEntityMock();
        $query = $this->createQueryMock($entity);
        $this->entityManager->method('createQuery')->willReturn($query);

        $result = $this->repository->findById(InvoiceId::generate());

        self::assertInstanceOf(Invoice::class, $result);
    }

    // -----------------------------------------------------------------------
    // findByNumber()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByNumberReturnsNullWhenNotFound(): void
    {
        $query = $this->createQueryMock(null);
        $this->entityManager->method('createQuery')->willReturn($query);

        $result = $this->repository->findByNumber($this->tenantId, InvoiceNumber::fromString('INV-2026-000001'));

        self::assertNull($result);
    }

    #[Test]
    public function findByNumberReturnsDomainModelWhenFound(): void
    {
        $entity = $this->createInvoiceEntityMock();
        $query = $this->createQueryMock($entity);
        $this->entityManager->method('createQuery')->willReturn($query);

        $result = $this->repository->findByNumber($this->tenantId, InvoiceNumber::fromString('INV-2026-000001'));

        self::assertInstanceOf(Invoice::class, $result);
    }

    // -----------------------------------------------------------------------
    // findByOrderId()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByOrderIdReturnsNullWhenNotFound(): void
    {
        $query = $this->createQueryMock(null);
        $this->entityManager->method('createQuery')->willReturn($query);

        $result = $this->repository->findByOrderId(OrderId::fromString('00000000-0000-4000-8000-000000000002'));

        self::assertNull($result);
    }

    #[Test]
    public function findByOrderIdReturnsDomainModelWhenFound(): void
    {
        $entity = $this->createInvoiceEntityMock();
        $query = $this->createQueryMock($entity);
        $this->entityManager->method('createQuery')->willReturn($query);

        $result = $this->repository->findByOrderId(OrderId::fromString('00000000-0000-4000-8000-000000000002'));

        self::assertInstanceOf(Invoice::class, $result);
    }

    // -----------------------------------------------------------------------
    // findByCustomerId()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByCustomerIdReturnsEmptyArrayWhenNone(): void
    {
        $query = $this->createQueryMock([], collection: true);
        $this->entityManager->method('createQuery')->willReturn($query);

        $result = $this->repository->findByCustomerId(CustomerId::fromString('00000000-0000-4000-8000-000000000010'));

        self::assertSame([], $result);
    }

    #[Test]
    public function findByCustomerIdReturnsMappedDomainModels(): void
    {
        $entity = $this->createInvoiceEntityMock();
        $query = $this->createQueryMock([$entity], collection: true);
        $this->entityManager->method('createQuery')->willReturn($query);

        $result = $this->repository->findByCustomerId(CustomerId::fromString('00000000-0000-4000-8000-000000000010'));

        self::assertCount(1, $result);
        self::assertInstanceOf(Invoice::class, $result[0]);
    }

    // -----------------------------------------------------------------------
    // findByTenantId()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByTenantIdReturnsEmptyArrayWhenNone(): void
    {
        $query = $this->createQueryMock([], collection: true);
        $this->entityManager->method('createQuery')->willReturn($query);

        $result = $this->repository->findByTenantId($this->tenantId);

        self::assertSame([], $result);
    }

    #[Test]
    public function findByTenantIdReturnsMappedDomainModels(): void
    {
        $entity = $this->createInvoiceEntityMock();
        $query = $this->createQueryMock([$entity], collection: true);
        $this->entityManager->method('createQuery')->willReturn($query);

        $result = $this->repository->findByTenantId($this->tenantId);

        self::assertCount(1, $result);
    }

    // -----------------------------------------------------------------------
    // findOverdue()
    // -----------------------------------------------------------------------

    #[Test]
    public function findOverdueReturnsEmptyArrayWhenNone(): void
    {
        $query = $this->createQueryMock([], collection: true);
        $this->entityManager->method('createQuery')->willReturn($query);

        $result = $this->repository->findOverdue($this->tenantId);

        self::assertSame([], $result);
    }

    #[Test]
    public function findOverdueReturnsMappedDomainModels(): void
    {
        $entity = $this->createInvoiceEntityMock();
        $query = $this->createQueryMock([$entity], collection: true);
        $this->entityManager->method('createQuery')->willReturn($query);

        $result = $this->repository->findOverdue($this->tenantId);

        self::assertCount(1, $result);
    }

    // -----------------------------------------------------------------------
    // delete()
    // -----------------------------------------------------------------------

    #[Test]
    public function deleteRemovesEntity(): void
    {
        $entity = $this->createMock(InvoiceEntity::class);
        $this->entityManager->method('find')->willReturn($entity);

        $this->entityManager->expects(self::once())->method('remove')->with($entity);
        $this->entityManager->expects(self::once())->method('flush');

        $this->repository->delete(InvoiceId::generate());
    }

    #[Test]
    public function deleteThrowsWhenNotFound(): void
    {
        $this->entityManager->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invoice with ID');

        $this->repository->delete(InvoiceId::generate());
    }

    // -----------------------------------------------------------------------
    // nextIdentity()
    // -----------------------------------------------------------------------

    #[Test]
    public function nextIdentityReturnsInvoiceId(): void
    {
        $result = $this->repository->nextIdentity();

        self::assertInstanceOf(InvoiceId::class, $result);
    }
}
