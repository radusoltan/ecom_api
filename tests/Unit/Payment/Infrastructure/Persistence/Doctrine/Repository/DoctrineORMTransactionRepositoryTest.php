<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Persistence\Doctrine\Repository;

use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\Transaction;
use App\Payment\Domain\Model\TransactionId;
use App\Payment\Domain\Model\TransactionStatus;
use App\Payment\Domain\Model\TransactionType;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\TransactionEntity;
use App\Payment\Infrastructure\Persistence\Doctrine\Repository\DoctrineORMTransactionRepository;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\Money;
use Brick\Money\Currency;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineORMTransactionRepository::class)]
final class DoctrineORMTransactionRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private TenantContextInterface&MockObject $tenantContext;
    private DoctrineORMTransactionRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);
        $this->tenantContext->method('getTenantId')->willReturn('00000000-0000-4000-8000-000000000001');
        $this->repository = new DoctrineORMTransactionRepository($this->entityManager, $this->tenantContext);
    }

    private function createTransactionDomainMock(): Transaction&MockObject
    {
        $txId = $this->createMock(TransactionId::class);
        $txId->method('toString')->willReturn('00000000-0000-4000-8000-000000000010');

        $paymentId = $this->createMock(PaymentId::class);
        $paymentId->method('toString')->willReturn('00000000-0000-4000-8000-000000000020');

        $currency = $this->createMock(Currency::class);
        $currency->method('getCurrencyCode')->willReturn('USD');

        $amount = $this->createMock(Money::class);
        $amount->method('getAmount')->willReturn(1000);
        $amount->method('getCurrency')->willReturn($currency);

        $transaction = $this->createMock(Transaction::class);
        $transaction->method('id')->willReturn($txId);
        $transaction->method('paymentId')->willReturn($paymentId);
        $transaction->method('type')->willReturn(TransactionType::CAPTURE);
        $transaction->method('status')->willReturn(TransactionStatus::SUCCESS);
        $transaction->method('amount')->willReturn($amount);
        $transaction->method('gatewayTransactionId')->willReturn('gw_txn_123');
        $transaction->method('gatewayResponse')->willReturn('{"status":"ok"}');
        $transaction->method('errorCode')->willReturn(null);
        $transaction->method('errorMessage')->willReturn(null);
        $transaction->method('createdAt')->willReturn(new \DateTimeImmutable());

        return $transaction;
    }

    #[Test]
    public function savePersistsAndFlushes(): void
    {
        $transaction = $this->createTransactionDomainMock();

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $this->repository->save($transaction);
    }

    #[Test]
    public function findByIdReturnsNullWhenNotFound(): void
    {
        $this->entityManager->method('find')->willReturn(null);

        $id = $this->createMock(TransactionId::class);
        $id->method('toString')->willReturn('00000000-0000-4000-8000-000000000010');

        $result = $this->repository->findById($id);

        self::assertNull($result);
    }

    #[Test]
    public function findByIdReturnsDomainModelWhenFound(): void
    {
        $entity = $this->createMock(TransactionEntity::class);
        $domainModel = $this->createMock(Transaction::class);
        $entity->method('toDomainModel')->willReturn($domainModel);

        $this->entityManager->method('find')->willReturn($entity);

        $id = $this->createMock(TransactionId::class);
        $id->method('toString')->willReturn('00000000-0000-4000-8000-000000000010');

        $result = $this->repository->findById($id);

        self::assertInstanceOf(Transaction::class, $result);
    }

    #[Test]
    public function findByPaymentIdReturnsEmptyArrayWhenNone(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([]);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $paymentId = $this->createMock(PaymentId::class);
        $paymentId->method('toString')->willReturn('00000000-0000-4000-8000-000000000020');

        $result = $this->repository->findByPaymentId($paymentId);

        self::assertSame([], $result);
    }

    #[Test]
    public function findByPaymentIdReturnsMappedDomainModels(): void
    {
        $entity = $this->createMock(TransactionEntity::class);
        $domainModel = $this->createMock(Transaction::class);
        $entity->method('toDomainModel')->willReturn($domainModel);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([$entity]);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $paymentId = $this->createMock(PaymentId::class);
        $paymentId->method('toString')->willReturn('00000000-0000-4000-8000-000000000020');

        $result = $this->repository->findByPaymentId($paymentId);

        self::assertCount(1, $result);
        self::assertInstanceOf(Transaction::class, $result[0]);
    }

    #[Test]
    public function findByGatewayTransactionIdReturnsNullWhenNotFound(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $result = $this->repository->findByGatewayTransactionId('gw_txn_123');

        self::assertNull($result);
    }

    #[Test]
    public function findByGatewayTransactionIdReturnsDomainModelWhenFound(): void
    {
        $entity = $this->createMock(TransactionEntity::class);
        $domainModel = $this->createMock(Transaction::class);
        $entity->method('toDomainModel')->willReturn($domainModel);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($entity);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $result = $this->repository->findByGatewayTransactionId('gw_txn_123');

        self::assertInstanceOf(Transaction::class, $result);
    }
}
