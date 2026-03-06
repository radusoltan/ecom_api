<?php

declare(strict_types=1);

namespace App\Tests\Integration\Returns\Infrastructure\Repository;

use App\Order\Domain\Model\OrderId;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnReason;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for DoctrineORMReturnRequestRepository.
 *
 * Tests database persistence with real Doctrine ORM and PostgreSQL RLS.
 */
final class DoctrineORMReturnRequestRepositoryTest extends KernelTestCase
{
    use TenantTestTrait;

    private ReturnRequestRepositoryInterface $repository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());

        $container = static::getContainer();
        $this->repository = $container->get(ReturnRequestRepositoryInterface::class);

        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    private function cleanupTestData(): void
    {
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();

        $connection->executeStatement('DELETE FROM return_requests');
    }

    // ============================================
    // save() + findById() roundtrip
    // ============================================

    public function testSaveAndFindById(): void
    {
        $returnRequest = $this->createReturnRequest();
        $id = $returnRequest->id();

        $this->repository->save($returnRequest);

        $found = $this->repository->findById($id);

        $this->assertNotNull($found);
        $this->assertSame($id->toString(), $found->id()->toString());
        $this->assertTrue($found->status()->isRequested());
        $this->assertSame('Product arrived with visible scratches on surface', $found->reason()->value());
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $result = $this->repository->findById(ReturnRequestId::generate());

        $this->assertNull($result);
    }

    // ============================================
    // save() - update existing
    // ============================================

    public function testSaveUpdatesExistingReturnRequest(): void
    {
        $returnRequest = $this->createReturnRequest();
        $this->repository->save($returnRequest);

        // Re-fetch, then approve
        $found = $this->repository->findById($returnRequest->id());
        $this->assertNotNull($found);

        $found->approve();
        $this->repository->save($found);

        // Re-fetch again
        $updated = $this->repository->findById($returnRequest->id());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->status()->isApproved());
    }

    // ============================================
    // Full lifecycle roundtrip
    // ============================================

    public function testFullLifecycleRoundtrip(): void
    {
        // Create
        $returnRequest = $this->createReturnRequest();
        $this->repository->save($returnRequest);

        // Approve
        $rr = $this->repository->findById($returnRequest->id());
        $this->assertNotNull($rr);
        $rr->approve();
        $this->repository->save($rr);

        // Mark received
        $rr = $this->repository->findById($returnRequest->id());
        $this->assertNotNull($rr);
        $rr->markAsReceived('00000000-0000-4000-8000-000000000042');
        $this->repository->save($rr);

        // Inspect
        $rr = $this->repository->findById($returnRequest->id());
        $this->assertNotNull($rr);
        $rr->inspect(true, 'Item in good condition');
        $this->repository->save($rr);

        // Complete
        $rr = $this->repository->findById($returnRequest->id());
        $this->assertNotNull($rr);
        $rr->complete(Money::fromScalars(2500, 'EUR'));
        $this->repository->save($rr);

        // Verify final state
        $final = $this->repository->findById($returnRequest->id());
        $this->assertNotNull($final);
        $this->assertTrue($final->status()->isCompleted());
        $this->assertSame('00000000-0000-4000-8000-000000000042', $final->warehouseId());
        $this->assertTrue($final->isResellable());
        $this->assertSame('Item in good condition', $final->inspectionNotes());
        $this->assertNotNull($final->refundAmount());
        $this->assertEquals(2500, $final->refundAmount()->getAmount());
    }

    // ============================================
    // findByOrderId()
    // ============================================

    public function testFindByOrderId(): void
    {
        $orderId = OrderId::generate();

        $rr1 = ReturnRequest::create(
            id: ReturnRequestId::generate(),
            tenantId: $this->tenantId,
            orderId: $orderId,
            reason: ReturnReason::fromString('First item was damaged during shipping'),
        );
        $this->repository->save($rr1);

        $rr2 = ReturnRequest::create(
            id: ReturnRequestId::generate(),
            tenantId: $this->tenantId,
            orderId: $orderId,
            reason: ReturnReason::fromString('Second item was the wrong color delivered'),
        );
        $this->repository->save($rr2);

        // Different order
        $rr3 = ReturnRequest::create(
            id: ReturnRequestId::generate(),
            tenantId: $this->tenantId,
            orderId: OrderId::generate(),
            reason: ReturnReason::fromString('This item is for a different order entirely'),
        );
        $this->repository->save($rr3);

        $results = $this->repository->findByOrderId($orderId);

        $this->assertCount(2, $results);
    }

    // ============================================
    // findByStatus()
    // ============================================

    public function testFindByStatus(): void
    {
        // Create two returns, approve one
        $rr1 = $this->createReturnRequest();
        $this->repository->save($rr1);

        $rr2 = $this->createReturnRequest();
        $this->repository->save($rr2);

        // Approve rr2
        $found = $this->repository->findById($rr2->id());
        $this->assertNotNull($found);
        $found->approve();
        $this->repository->save($found);

        $requested = $this->repository->findByStatus('requested', $this->tenantId);
        $approved = $this->repository->findByStatus('approved', $this->tenantId);

        $this->assertCount(1, $requested);
        $this->assertCount(1, $approved);
    }

    // ============================================
    // Helpers
    // ============================================

    private function createReturnRequest(): ReturnRequest
    {
        return ReturnRequest::create(
            id: ReturnRequestId::generate(),
            tenantId: $this->tenantId,
            orderId: OrderId::generate(),
            reason: ReturnReason::fromString('Product arrived with visible scratches on surface'),
        );
    }
}
