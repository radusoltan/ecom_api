<?php

declare(strict_types=1);

namespace App\Tests\Integration\Payment\Infrastructure\Persistence\Doctrine\Repository;

use App\Payment\Domain\Event\PaymentAuthorized;
use App\Payment\Domain\Event\PaymentCancelled;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\Event\PaymentCreated;
use App\Payment\Domain\Event\PaymentFailed;
use App\Payment\Domain\Event\PaymentRefunded;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Payment\Infrastructure\Persistence\Doctrine\Repository\DoctrineORMPaymentRepository;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class DoctrineORMPaymentRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineORMPaymentRepository $repository;
    private EventDispatcherInterface $eventDispatcher;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->eventDispatcher = $container->get(EventDispatcherInterface::class);
        $this->repository = new DoctrineORMPaymentRepository(
            $this->entityManager,
            $this->eventDispatcher
        );

        $this->tenantId = TenantId::generate();

        // Start transaction for test isolation
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback transaction to isolate tests
        $this->entityManager->rollback();
        $this->entityManager->close();

        parent::tearDown();
    }

    public function testSaveAndFindById(): void
    {
        // Arrange
        $payment = $this->createPayment();

        // Act
        $this->repository->save($payment);

        // Assert
        $foundPayment = $this->repository->findById($payment->id(), $this->tenantId);
        $this->assertNotNull($foundPayment);
        $this->assertTrue($foundPayment->id()->equals($payment->id()));
        $this->assertTrue($foundPayment->tenantId()->equals($payment->tenantId()));
        $this->assertSame($payment->orderId(), $foundPayment->orderId());
        $this->assertSame($payment->amountInCents(), $foundPayment->amountInCents());
        $this->assertSame($payment->currency(), $foundPayment->currency());
    }

    public function testFindByIdReturnsNullForNonExistentPayment(): void
    {
        // Arrange
        $nonExistentId = PaymentId::generate();

        // Act
        $foundPayment = $this->repository->findById($nonExistentId, $this->tenantId);

        // Assert
        $this->assertNull($foundPayment);
    }

    public function testFindByOrderId(): void
    {
        // Arrange
        $orderId = '01JCEX' . bin2hex(random_bytes(10)); // 26 chars ULID format
        $payment = $this->createPayment(orderId: $orderId);
        $this->repository->save($payment);

        // Act
        $foundPayments = $this->repository->findByOrderId($orderId, $this->tenantId);

        // Assert
        $this->assertIsArray($foundPayments);
        $this->assertCount(1, $foundPayments);
        $this->assertSame($orderId, $foundPayments[0]->orderId());
    }

    public function testFindByOrderIdReturnsEmptyArrayWhenNotFound(): void
    {
        // Act
        $foundPayments = $this->repository->findByOrderId('NON_EXISTENT_ORDER', $this->tenantId);

        // Assert
        $this->assertIsArray($foundPayments);
        $this->assertEmpty($foundPayments);
    }

    public function testFindAll(): void
    {
        // Arrange
        $payment1 = $this->createPayment();
        $payment2 = $this->createPayment();
        $this->repository->save($payment1);
        $this->repository->save($payment2);

        // Act
        $payments = $this->repository->findAll($this->tenantId);

        // Assert
        $this->assertCount(2, $payments);
        foreach ($payments as $payment) {
            $this->assertTrue($payment->tenantId()->equals($this->tenantId));
        }
    }

    public function testFindAllReturnsEmptyArrayWhenNoneFound(): void
    {
        // Arrange
        $otherTenantId = TenantId::generate();

        // Act
        $payments = $this->repository->findAll($otherTenantId);

        // Assert
        $this->assertIsArray($payments);
        $this->assertEmpty($payments);
    }

    public function testUpdatePayment(): void
    {
        // Arrange
        $payment = $this->createPayment();
        $this->repository->save($payment);

        // Act - Authorize payment
        $payment->authorize('pi_abc123xyz');
        $this->repository->save($payment);
        $this->entityManager->clear(); // Clear to force fresh fetch

        // Assert
        $updatedPayment = $this->repository->findById($payment->id(), $this->tenantId);
        $this->assertNotNull($updatedPayment);
        $this->assertTrue($updatedPayment->status()->isAuthorized());
        $this->assertSame('pi_abc123xyz', $updatedPayment->gatewayTransactionId());
    }

    public function testPaymentLifecycleWithMultipleUpdates(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $payment = Payment::create(
            id: $paymentId,
            tenantId: $this->tenantId,
            orderId: '01JCEX' . bin2hex(random_bytes(10)), // 26 chars ULID format
            amountInCents: 9999,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
        $this->repository->save($payment);
        $this->entityManager->clear();

        // Act 1 - Authorize
        $payment = $this->repository->findById($paymentId, $this->tenantId);
        $payment->authorize('pi_test123');
        $this->repository->save($payment);
        $this->entityManager->clear();

        $payment = $this->repository->findById($paymentId, $this->tenantId);
        $this->assertTrue($payment->status()->isAuthorized());

        // Act 2 - Capture
        $payment->capture();
        $this->repository->save($payment);
        $this->entityManager->clear();

        $payment = $this->repository->findById($paymentId, $this->tenantId);
        $this->assertTrue($payment->status()->isCaptured());

        // Act 3 - Partial Refund
        $payment->refund(5000, 'Customer requested partial refund');
        $this->repository->save($payment);
        $this->entityManager->clear();

        // Assert
        $finalPayment = $this->repository->findById($paymentId, $this->tenantId);
        $this->assertNotNull($finalPayment);
        $this->assertTrue($finalPayment->status()->isRefunded());
        $this->assertSame(5000, $finalPayment->refundedAmountInCents());
    }

    public function testDomainEventsAreDispatchedAfterSave(): void
    {
        // Arrange
        $dispatchedEvents = [];
        $mockDispatcher = $this->createMock(EventDispatcherInterface::class);
        $mockDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $repository = new DoctrineORMPaymentRepository(
            $this->entityManager,
            $mockDispatcher
        );

        $payment = $this->createPayment();

        // Act
        $repository->save($payment);

        // Assert
        $this->assertCount(1, $dispatchedEvents);
        $this->assertInstanceOf(PaymentCreated::class, $dispatchedEvents[0]);
    }

    public function testMultipleDomainEventsDispatchedForStateChanges(): void
    {
        // Arrange
        $dispatchedEvents = [];
        $mockDispatcher = $this->createMock(EventDispatcherInterface::class);
        $mockDispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $repository = new DoctrineORMPaymentRepository(
            $this->entityManager,
            $mockDispatcher
        );

        $payment = $this->createPayment();
        $repository->save($payment); // Dispatches PaymentCreated
        $this->entityManager->flush();

        // Act
        $payment->authorize('pi_test123');
        $repository->save($payment); // Dispatches PaymentAuthorized
        $this->entityManager->flush();

        $payment->capture();
        $repository->save($payment); // Dispatches PaymentCaptured
        $this->entityManager->flush();

        // Assert
        $this->assertCount(3, $dispatchedEvents);
        $this->assertInstanceOf(PaymentCreated::class, $dispatchedEvents[0]);
        $this->assertInstanceOf(PaymentAuthorized::class, $dispatchedEvents[1]);
        $this->assertInstanceOf(PaymentCaptured::class, $dispatchedEvents[2]);
    }

    public function testEntityRefreshPreservesChanges(): void
    {
        // Arrange
        $payment = $this->createPayment();
        $this->repository->save($payment);

        // Act - Update and refresh
        $payment->authorize('pi_test123');
        $this->repository->save($payment);
        $this->entityManager->clear();

        // Assert - Changes should persist after clear
        $refreshedPayment = $this->repository->findById($payment->id(), $this->tenantId);
        $this->assertNotNull($refreshedPayment);
        $this->assertTrue($refreshedPayment->status()->isAuthorized());
        $this->assertSame('pi_test123', $refreshedPayment->gatewayTransactionId());
    }

    public function testMultiTenantIsolation(): void
    {
        // Arrange
        $tenant1 = TenantId::generate();
        $tenant2 = TenantId::generate();

        $payment1 = $this->createPayment(tenantId: $tenant1);
        $payment2 = $this->createPayment(tenantId: $tenant2);

        $this->repository->save($payment1);
        $this->repository->save($payment2);

        // Act
        $tenant1Payments = $this->repository->findAll($tenant1);
        $tenant2Payments = $this->repository->findAll($tenant2);

        // Assert
        $this->assertCount(1, $tenant1Payments);
        $this->assertCount(1, $tenant2Payments);
        $this->assertTrue($tenant1Payments[0]->tenantId()->equals($tenant1));
        $this->assertTrue($tenant2Payments[0]->tenantId()->equals($tenant2));
    }

    public function testCancelledPaymentPersistence(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $payment = Payment::create(
            id: $paymentId,
            tenantId: $this->tenantId,
            orderId: '01JCEX' . bin2hex(random_bytes(10)), // 26 chars ULID format
            amountInCents: 9999,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
        $this->repository->save($payment);
        $this->entityManager->clear();

        // Act
        $payment = $this->repository->findById($paymentId, $this->tenantId);
        $payment->cancel('Customer cancelled order');
        $this->repository->save($payment);
        $this->entityManager->clear();

        // Assert
        $cancelledPayment = $this->repository->findById($paymentId, $this->tenantId);
        $this->assertNotNull($cancelledPayment);
        $this->assertTrue($cancelledPayment->status()->isCancelled());
    }

    public function testFailedPaymentPersistence(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $payment = Payment::create(
            id: $paymentId,
            tenantId: $this->tenantId,
            orderId: '01JCEX' . bin2hex(random_bytes(10)), // 26 chars ULID format
            amountInCents: 9999,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
        $this->repository->save($payment);
        $this->entityManager->clear();

        // Act
        $payment = $this->repository->findById($paymentId, $this->tenantId);
        $errorMessage = 'Card declined by issuer';
        $payment->markAsFailed($errorMessage);
        $this->repository->save($payment);
        $this->entityManager->clear();

        // Assert
        $failedPayment = $this->repository->findById($paymentId, $this->tenantId);
        $this->assertNotNull($failedPayment);
        $this->assertTrue($failedPayment->status()->isFailed());
        $this->assertSame($errorMessage, $failedPayment->errorMessage());
    }

    // Helper methods
    private function createPayment(
        ?TenantId $tenantId = null,
        ?string $orderId = null
    ): Payment {
        return Payment::create(
            id: PaymentId::generate(),
            tenantId: $tenantId ?? $this->tenantId,
            orderId: $orderId ?? '01JCEX' . bin2hex(random_bytes(10)), // 6 + 20 = 26 chars (ULID format)
            amountInCents: 9999,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
    }
}
