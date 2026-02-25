<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Application\Command;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Application\Command\MarkInvoicePaid\MarkInvoicePaidCommand;
use App\Invoice\Application\Command\MarkInvoicePaid\MarkInvoicePaidHandler;
use App\Invoice\Domain\Model\BillingAddress;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceLine;
use App\Invoice\Domain\Model\InvoiceLineId;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(MarkInvoicePaidHandler::class)]
final class MarkInvoicePaidHandlerTest extends TestCase
{
    private InvoiceRepositoryInterface $repository;
    private MarkInvoicePaidHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->handler = new MarkInvoicePaidHandler($this->repository, new NullLogger());
    }

    #[Test]
    public function handleMarksPaidAndSaves(): void
    {
        $invoiceId = InvoiceId::generate();
        $invoice = $this->createIssuedInvoice($invoiceId);

        $command = new MarkInvoicePaidCommand($invoiceId->toString(), '2025-03-15');

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn($invoice);

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Invoice $saved): bool {
                return $saved->status()->isPaid()
                    && '2025-03-15' === $saved->paidDate()->format('Y-m-d');
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function handleThrowsExceptionWhenNotFound(): void
    {
        $command = new MarkInvoicePaidCommand(InvoiceId::generate()->toString(), '2025-03-15');

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);

        ($this->handler)($command);
    }

    private function createIssuedInvoice(InvoiceId $id): Invoice
    {
        $invoice = Invoice::createDraft(
            $id,
            TenantId::generate(),
            OrderId::generate(),
            CustomerId::generate(),
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'US')
        );
        $invoice->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Test Item',
            1,
            Money::fromScalars(1000, 'USD'),
            20.0
        ));
        $invoice->issue(InvoiceNumber::create(2025, 1), new \DateTimeImmutable('2025-03-01'));

        return $invoice;
    }
}
