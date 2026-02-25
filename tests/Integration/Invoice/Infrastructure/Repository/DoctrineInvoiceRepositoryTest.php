<?php

declare(strict_types=1);

namespace App\Tests\Integration\Invoice\Infrastructure\Repository;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Domain\Model\BillingAddress;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceLine;
use App\Invoice\Domain\Model\InvoiceLineId;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Invoice\Infrastructure\Persistence\Doctrine\Entity\InvoiceEntity;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineInvoiceRepositoryTest extends KernelTestCase
{
    use TenantTestTrait;

    private InvoiceRepositoryInterface $repository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());

        $container = static::getContainer();
        $this->repository = $container->get(InvoiceRepositoryInterface::class);

        $this->cleanupTestInvoices();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestInvoices();
        parent::tearDown();
    }

    private function cleanupTestInvoices(): void
    {
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();

        $connection->executeStatement('DELETE FROM invoice_lines');
        $connection->executeStatement('DELETE FROM invoices');
    }

    // ============================================
    // save() Tests
    // ============================================

    public function testSaveInsertsDraftInvoice(): void
    {
        $invoiceId = InvoiceId::generate();
        $orderId = OrderId::generate();
        $customerId = CustomerId::generate();

        $invoice = Invoice::createDraft(
            $invoiceId,
            $this->tenantId,
            $orderId,
            $customerId,
            BillingAddress::create('John Doe', '123 Main Street', null, 'Amsterdam', '1011 AB', 'NL')
        );
        $invoice->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Widget A',
            2,
            Money::fromScalars(1000, 'EUR'),
            21.0
        ));

        $this->repository->save($invoice);

        $found = $this->repository->findById($invoiceId);

        $this->assertNotNull($found);
        $this->assertSame($invoiceId->toString(), $found->id()->toString());
        $this->assertTrue($found->status()->isDraft());
        $this->assertCount(1, $found->lines());
        $this->assertSame(2000, $found->subtotal()->getAmount());
    }

    public function testSaveUpdatesExistingInvoice(): void
    {
        $invoiceId = InvoiceId::generate();
        $invoice = Invoice::createDraft(
            $invoiceId,
            $this->tenantId,
            OrderId::generate(),
            CustomerId::generate(),
            BillingAddress::create('John Doe', '123 Main Street', null, 'Amsterdam', '1011 AB', 'NL')
        );
        $invoice->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Widget A',
            2,
            Money::fromScalars(1000, 'EUR'),
            21.0
        ));
        $this->repository->save($invoice);

        // Clear EM and reload, then modify
        $this->clearEntityManager();
        $reloaded = $this->repository->findById($invoiceId);
        $this->assertNotNull($reloaded);

        // Add a second line via fresh domain model
        $reloaded->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Widget B',
            1,
            Money::fromScalars(500, 'EUR'),
            21.0
        ));
        $this->repository->save($reloaded);

        $this->clearEntityManager();
        $found = $this->repository->findById($invoiceId);

        $this->assertNotNull($found);
        $this->assertCount(2, $found->lines());
    }

    public function testSaveIssuedInvoice(): void
    {
        $invoiceId = InvoiceId::generate();
        $invoice = Invoice::createDraft(
            $invoiceId,
            $this->tenantId,
            OrderId::generate(),
            CustomerId::generate(),
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'NL')
        );
        $invoice->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Item',
            1,
            Money::fromScalars(1000, 'EUR'),
            21.0
        ));
        $this->repository->save($invoice);

        // Clear EM, reload, then issue
        $this->clearEntityManager();
        $reloaded = $this->repository->findById($invoiceId);
        $this->assertNotNull($reloaded);

        $reloaded->issue(InvoiceNumber::create(2025, 42), new \DateTimeImmutable('2025-06-01'));
        $this->repository->save($reloaded);

        $this->clearEntityManager();
        $found = $this->repository->findById($invoiceId);

        $this->assertNotNull($found);
        $this->assertTrue($found->status()->isIssued());
        $this->assertNotNull($found->number());
        $this->assertSame('INV-2025-000042', $found->number()->toString());
        $this->assertNotNull($found->issueDate());
        $this->assertNotNull($found->dueDate());
    }

    // ============================================
    // findById() Tests
    // ============================================

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findById(InvoiceId::generate());

        $this->assertNull($result);
    }

    public function testFindByIdReturnsInvoiceWithLines(): void
    {
        $invoice = $this->createAndSaveDraftInvoice();

        $found = $this->repository->findById($invoice->id());

        $this->assertNotNull($found);
        $this->assertCount(1, $found->lines());
        $line = $found->lines()[0];
        $this->assertSame('Widget A', $line->description());
        $this->assertSame(2, $line->quantity());
        $this->assertSame(1000, $line->unitPrice()->getAmount());
    }

    // ============================================
    // findByOrderId() Tests
    // ============================================

    public function testFindByOrderIdReturnsInvoice(): void
    {
        $orderId = OrderId::generate();
        $invoice = Invoice::createDraft(
            InvoiceId::generate(),
            $this->tenantId,
            $orderId,
            CustomerId::generate(),
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'NL')
        );
        $invoice->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Item',
            1,
            Money::fromScalars(1000, 'EUR'),
            21.0
        ));
        $this->repository->save($invoice);

        $found = $this->repository->findByOrderId($orderId);

        $this->assertNotNull($found);
        $this->assertSame($invoice->id()->toString(), $found->id()->toString());
    }

    public function testFindByOrderIdReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByOrderId(OrderId::generate());

        $this->assertNull($result);
    }

    // ============================================
    // findByCustomerId() Tests
    // ============================================

    public function testFindByCustomerIdReturnsInvoices(): void
    {
        $customerId = CustomerId::generate();

        $invoice1 = Invoice::createDraft(
            InvoiceId::generate(),
            $this->tenantId,
            OrderId::generate(),
            $customerId,
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'NL')
        );
        $invoice1->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Item 1',
            1,
            Money::fromScalars(1000, 'EUR'),
            21.0
        ));

        $invoice2 = Invoice::createDraft(
            InvoiceId::generate(),
            $this->tenantId,
            OrderId::generate(),
            $customerId,
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'NL')
        );
        $invoice2->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Item 2',
            1,
            Money::fromScalars(2000, 'EUR'),
            21.0
        ));

        $this->repository->save($invoice1);
        $this->repository->save($invoice2);

        $found = $this->repository->findByCustomerId($customerId);

        $this->assertCount(2, $found);
    }

    public function testFindByCustomerIdReturnsEmptyWhenNoneFound(): void
    {
        $result = $this->repository->findByCustomerId(CustomerId::generate());

        $this->assertEmpty($result);
    }

    // ============================================
    // findOverdue() Tests
    // ============================================

    public function testFindOverdueReturnsOverdueInvoices(): void
    {
        $invoiceId = InvoiceId::generate();
        $invoice = Invoice::createDraft(
            $invoiceId,
            $this->tenantId,
            OrderId::generate(),
            CustomerId::generate(),
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'NL')
        );
        $invoice->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Item',
            1,
            Money::fromScalars(1000, 'EUR'),
            21.0
        ));
        $this->repository->save($invoice);

        // Clear EM, reload, issue with past due date
        $this->clearEntityManager();
        $reloaded = $this->repository->findById($invoiceId);
        $reloaded->issue(InvoiceNumber::create(2025, 99), new \DateTimeImmutable('-60 days'));
        $this->repository->save($reloaded);

        $this->clearEntityManager();
        $overdue = $this->repository->findOverdue($this->tenantId);

        $this->assertCount(1, $overdue);
        $this->assertSame($invoiceId->toString(), $overdue[0]->id()->toString());
    }

    public function testFindOverdueExcludesPaidInvoices(): void
    {
        $invoiceId = InvoiceId::generate();
        $invoice = Invoice::createDraft(
            $invoiceId,
            $this->tenantId,
            OrderId::generate(),
            CustomerId::generate(),
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'NL')
        );
        $invoice->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Item',
            1,
            Money::fromScalars(1000, 'EUR'),
            21.0
        ));
        $this->repository->save($invoice);

        // Clear EM, reload, issue, then mark as paid
        $this->clearEntityManager();
        $reloaded = $this->repository->findById($invoiceId);
        $reloaded->issue(InvoiceNumber::create(2025, 100), new \DateTimeImmutable('-60 days'));
        $reloaded->markAsPaid(new \DateTimeImmutable());
        $this->repository->save($reloaded);

        $this->clearEntityManager();
        $overdue = $this->repository->findOverdue($this->tenantId);

        $this->assertEmpty($overdue);
    }

    // ============================================
    // delete() Tests
    // ============================================

    public function testDeleteRemovesInvoice(): void
    {
        $invoice = $this->createAndSaveDraftInvoice();

        $this->assertNotNull($this->repository->findById($invoice->id()));

        $this->repository->delete($invoice->id());

        $this->assertNull($this->repository->findById($invoice->id()));
    }

    public function testDeleteThrowsWhenNotFound(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->repository->delete(InvoiceId::generate());
    }

    // ============================================
    // Domain event dispatch
    // ============================================

    public function testSaveClearsEventsAfterDispatch(): void
    {
        $invoice = Invoice::createDraft(
            InvoiceId::generate(),
            $this->tenantId,
            OrderId::generate(),
            CustomerId::generate(),
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'NL')
        );
        $invoice->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Item',
            1,
            Money::fromScalars(1000, 'EUR'),
            21.0
        ));

        $this->repository->save($invoice);

        $this->assertCount(0, $invoice->popEvents());
    }

    // ============================================
    // Helpers
    // ============================================

    private function clearEntityManager(): void
    {
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();
    }

    private function createAndSaveDraftInvoice(): Invoice
    {
        $invoice = Invoice::createDraft(
            InvoiceId::generate(),
            $this->tenantId,
            OrderId::generate(),
            CustomerId::generate(),
            BillingAddress::create('John Doe', '123 Main Street', null, 'Amsterdam', '1011 AB', 'NL')
        );
        $invoice->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Widget A',
            2,
            Money::fromScalars(1000, 'EUR'),
            21.0
        ));

        $this->repository->save($invoice);

        return $invoice;
    }
}
