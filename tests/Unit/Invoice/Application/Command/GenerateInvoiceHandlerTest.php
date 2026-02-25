<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Application\Command;

use App\Invoice\Application\Command\GenerateInvoice\GenerateInvoiceCommand;
use App\Invoice\Application\Command\GenerateInvoice\GenerateInvoiceHandler;
use App\Invoice\Application\Command\GenerateInvoice\GenerateInvoiceLineDto;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(GenerateInvoiceHandler::class)]
final class GenerateInvoiceHandlerTest extends TestCase
{
    private InvoiceRepositoryInterface $repository;
    private GenerateInvoiceHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->handler = new GenerateInvoiceHandler($this->repository, new NullLogger());
    }

    #[Test]
    public function handleCreatesInvoiceWithLines(): void
    {
        $invoiceId = InvoiceId::generate();
        $command = new GenerateInvoiceCommand(
            invoiceId: $invoiceId->toString(),
            tenantId: '00000000-0000-4000-8000-000000000001',
            orderId: '00000000-0000-4000-8000-000000000010',
            customerId: '00000000-0000-4000-8000-000000000020',
            billingAddress: [
                'name' => 'John Doe',
                'addressLine1' => '123 Main Street',
                'city' => 'New York',
                'postalCode' => '10001',
                'country' => 'US',
            ],
            lines: [
                new GenerateInvoiceLineDto(
                    description: 'Widget A',
                    quantity: 2,
                    unitPriceAmount: 1000,
                    unitPriceCurrency: 'USD',
                    taxRate: 20.0
                ),
            ],
        );

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Invoice $invoice) use ($invoiceId): bool {
                return $invoice->id()->equals($invoiceId)
                    && $invoice->status()->isDraft()
                    && count($invoice->lines()) === 1
                    && $invoice->subtotal()->getAmount() === 2000;
            }));

        $result = ($this->handler)($command);

        self::assertTrue($result->equals($invoiceId));
    }

    #[Test]
    public function handleCreatesReverseChargeInvoice(): void
    {
        $command = new GenerateInvoiceCommand(
            invoiceId: InvoiceId::generate()->toString(),
            tenantId: '00000000-0000-4000-8000-000000000001',
            orderId: '00000000-0000-4000-8000-000000000010',
            customerId: '00000000-0000-4000-8000-000000000020',
            billingAddress: [
                'name' => 'Acme GmbH',
                'addressLine1' => 'Hauptstrasse 42',
                'city' => 'Berlin',
                'postalCode' => '10115',
                'country' => 'DE',
                'vatNumber' => 'DE123456789',
            ],
            lines: [
                new GenerateInvoiceLineDto(
                    description: 'Consulting Service',
                    quantity: 1,
                    unitPriceAmount: 5000,
                    unitPriceCurrency: 'EUR',
                    taxRate: 19.0
                ),
            ],
            isReverseCharge: true,
        );

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Invoice $invoice): bool {
                return $invoice->isReverseCharge()
                    && $invoice->taxAmount()->getAmount() === 0;
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function handleCreatesInvoiceWithMultipleLines(): void
    {
        $command = new GenerateInvoiceCommand(
            invoiceId: InvoiceId::generate()->toString(),
            tenantId: '00000000-0000-4000-8000-000000000001',
            orderId: '00000000-0000-4000-8000-000000000010',
            customerId: '00000000-0000-4000-8000-000000000020',
            billingAddress: [
                'name' => 'John Doe',
                'addressLine1' => '123 Main Street',
                'city' => 'New York',
                'postalCode' => '10001',
                'country' => 'US',
            ],
            lines: [
                new GenerateInvoiceLineDto('Item A', 1, 1000, 'USD', 20.0),
                new GenerateInvoiceLineDto('Item B', 3, 500, 'USD', 10.0),
                new GenerateInvoiceLineDto('Item C', 2, 2000, 'USD', 0.0),
            ],
        );

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Invoice $invoice): bool {
                return count($invoice->lines()) === 3;
            }));

        ($this->handler)($command);
    }
}
