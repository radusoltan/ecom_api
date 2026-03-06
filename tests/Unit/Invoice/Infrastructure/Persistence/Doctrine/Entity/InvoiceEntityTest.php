<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Domain\Model\BillingAddress;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceLine;
use App\Invoice\Domain\Model\InvoiceLineId;
use App\Invoice\Infrastructure\Persistence\Doctrine\Entity\InvoiceEntity;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class InvoiceEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $invoiceId = InvoiceId::generate();
        $tenantId = TenantId::generate();
        $orderId = OrderId::generate();
        $customerId = CustomerId::generate();
        $address = BillingAddress::create('John Doe', '123 Main St', null, 'Berlin', '10115', 'DE', null);

        $invoice = Invoice::createDraft($invoiceId, $tenantId, $orderId, $customerId, $address, false, 'Test notes');

        // Add a line
        $line = InvoiceLine::create(InvoiceLineId::generate(), 'Test Product', 2, Money::fromScalars(1000, 'EUR'), 19.0, null, 'PRD-000001', 1);
        $invoice->addLine($line);

        // Convert to entity
        $entity = InvoiceEntity::fromDomainModel($invoice);

        // Verify entity properties
        self::assertSame($invoiceId->toString(), $entity->getId());
        self::assertSame($tenantId->toString(), $entity->getTenantId());
        self::assertSame($orderId->toString(), $entity->getOrderId());
        self::assertSame($customerId->toString(), $entity->getCustomerId());
        self::assertSame('draft', $entity->getStatus());
        self::assertSame('Test notes', $entity->getNotes());
        self::assertFalse($entity->isReverseCharge());

        // Convert back to domain model
        $restored = $entity->toDomainModel();

        self::assertTrue($restored->id()->equals($invoiceId));
        self::assertSame('draft', $restored->status()->value);
        self::assertSame('Test notes', $restored->notes());
        self::assertCount(1, $restored->lines());
    }

    public function testUpdateFromDomainModel(): void
    {
        $invoiceId = InvoiceId::generate();
        $tenantId = TenantId::generate();
        $orderId = OrderId::generate();
        $customerId = CustomerId::generate();
        $address = BillingAddress::create('John Doe', '123 Main St', null, 'Berlin', '10115', 'DE', null);

        $invoice = Invoice::createDraft($invoiceId, $tenantId, $orderId, $customerId, $address);
        $entity = InvoiceEntity::fromDomainModel($invoice);

        // Add line and update
        $line = InvoiceLine::create(InvoiceLineId::generate(), 'Updated Product', 1, Money::fromScalars(500, 'EUR'), 19.0);
        $invoice->addLine($line);

        $entity->updateFromDomainModel($invoice);

        self::assertCount(1, $entity->getLines());
    }

    public function testGetters(): void
    {
        $address = BillingAddress::create('Jane', '456 Oak Ave', 'Suite 2', 'Munich', '80331', 'DE', 'DE123456789');
        $invoice = Invoice::createDraft(InvoiceId::generate(), TenantId::generate(), OrderId::generate(), CustomerId::generate(), $address, true);

        $entity = InvoiceEntity::fromDomainModel($invoice);

        self::assertTrue($entity->isReverseCharge());
        self::assertIsArray($entity->getBillingAddress());
        self::assertSame(0, $entity->getSubtotalAmount());
        self::assertSame('USD', $entity->getSubtotalCurrency());
        self::assertSame(0, $entity->getTaxAmount());
        self::assertSame(0, $entity->getTotalAmount());
        self::assertIsArray($entity->getTaxBreakdown());
        self::assertNull($entity->getInvoiceNumber());
        self::assertNull($entity->getIssueDate());
        self::assertNull($entity->getDueDate());
        self::assertNull($entity->getPaidDate());
        self::assertNull($entity->getPdfPath());
        self::assertNull($entity->getCreditedInvoiceId());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());
    }
}
