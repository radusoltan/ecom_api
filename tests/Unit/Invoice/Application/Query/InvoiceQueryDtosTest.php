<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Application\Query;

use App\Invoice\Application\Query\DownloadInvoicePdf\DownloadInvoicePdfQuery;
use App\Invoice\Application\Query\DownloadInvoicePdf\InvoicePdfResult;
use App\Invoice\Application\Query\GetInvoice\GetInvoiceQuery;
use App\Invoice\Application\Query\GetInvoicesByCustomer\GetInvoicesByCustomerQuery;
use App\Invoice\Application\Query\GetInvoicesByOrder\GetInvoicesByOrderQuery;
use App\Invoice\Application\Query\GetInvoiceStatistics\GetInvoiceStatisticsQuery;
use App\Invoice\Application\Query\GetInvoiceStatistics\InvoiceStatisticsDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetInvoiceQuery::class)]
#[CoversClass(GetInvoicesByCustomerQuery::class)]
#[CoversClass(GetInvoicesByOrderQuery::class)]
#[CoversClass(GetInvoiceStatisticsQuery::class)]
#[CoversClass(DownloadInvoicePdfQuery::class)]
#[CoversClass(InvoicePdfResult::class)]
#[CoversClass(InvoiceStatisticsDto::class)]
final class InvoiceQueryDtosTest extends TestCase
{
    // -------------------------------------------------------
    // GetInvoiceQuery
    // -------------------------------------------------------

    #[Test]
    public function getInvoiceQueryStoresInvoiceId(): void
    {
        $invoiceId = '550e8400-e29b-41d4-a716-446655440000';

        $query = new GetInvoiceQuery($invoiceId);

        self::assertSame($invoiceId, $query->invoiceId);
    }

    // -------------------------------------------------------
    // GetInvoicesByCustomerQuery
    // -------------------------------------------------------

    #[Test]
    public function getInvoicesByCustomerQueryStoresCustomerId(): void
    {
        $customerId = '550e8400-e29b-41d4-a716-446655440001';

        $query = new GetInvoicesByCustomerQuery($customerId);

        self::assertSame($customerId, $query->customerId);
    }

    // -------------------------------------------------------
    // GetInvoicesByOrderQuery
    // -------------------------------------------------------

    #[Test]
    public function getInvoicesByOrderQueryStoresOrderId(): void
    {
        $orderId = '550e8400-e29b-41d4-a716-446655440002';

        $query = new GetInvoicesByOrderQuery($orderId);

        self::assertSame($orderId, $query->orderId);
    }

    // -------------------------------------------------------
    // GetInvoiceStatisticsQuery
    // -------------------------------------------------------

    #[Test]
    public function getInvoiceStatisticsQueryStoresTenantId(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';

        $query = new GetInvoiceStatisticsQuery($tenantId);

        self::assertSame($tenantId, $query->tenantId);
    }

    // -------------------------------------------------------
    // DownloadInvoicePdfQuery
    // -------------------------------------------------------

    #[Test]
    public function downloadInvoicePdfQueryStoresInvoiceId(): void
    {
        $invoiceId = '550e8400-e29b-41d4-a716-446655440003';

        $query = new DownloadInvoicePdfQuery($invoiceId);

        self::assertSame($invoiceId, $query->invoiceId);
    }

    #[Test]
    public function downloadInvoicePdfQueryDefaultsLocaleToEn(): void
    {
        $query = new DownloadInvoicePdfQuery('550e8400-e29b-41d4-a716-446655440003');

        self::assertSame('en', $query->locale);
    }

    #[Test]
    public function downloadInvoicePdfQueryAcceptsCustomLocale(): void
    {
        $query = new DownloadInvoicePdfQuery('550e8400-e29b-41d4-a716-446655440003', 'fr');

        self::assertSame('fr', $query->locale);
    }

    #[Test]
    public function downloadInvoicePdfQueryExposesInvoiceId(): void
    {
        $query = new DownloadInvoicePdfQuery('550e8400-e29b-41d4-a716-446655440003');

        self::assertSame('550e8400-e29b-41d4-a716-446655440003', $query->invoiceId);
    }

    // -------------------------------------------------------
    // InvoicePdfResult
    // -------------------------------------------------------

    #[Test]
    public function invoicePdfResultExposesAllProperties(): void
    {
        $content = '%PDF-1.4 binary content here';
        $fileName = 'INV-2025-000042.pdf';
        $mimeType = 'application/pdf';

        $result = new InvoicePdfResult($content, $fileName, $mimeType);

        self::assertSame($content, $result->content);
        self::assertSame($fileName, $result->fileName);
        self::assertSame($mimeType, $result->mimeType);
    }

    #[Test]
    public function invoicePdfResultDefaultMimeType(): void
    {
        $result = new InvoicePdfResult('%PDF', 'test.pdf', 'application/pdf');

        self::assertSame('application/pdf', $result->mimeType);
    }

    #[Test]
    public function invoicePdfResultPreservesEmptyBinaryContent(): void
    {
        $result = new InvoicePdfResult('', 'empty.pdf', 'application/pdf');

        self::assertSame('', $result->content);
    }

    // -------------------------------------------------------
    // InvoiceStatisticsDto
    // -------------------------------------------------------

    #[Test]
    public function invoiceStatisticsDtoExposesAllCounters(): void
    {
        $dto = new InvoiceStatisticsDto(
            totalInvoices: 150,
            draftCount: 5,
            issuedCount: 20,
            paidCount: 120,
            cancelledCount: 3,
            creditedCount: 2,
            overdueCount: 8,
            totalRevenueAmount: 1_500_000,
            totalRevenueCurrency: 'USD',
            outstandingAmount: 200_000,
            outstandingCurrency: 'USD',
        );

        self::assertSame(150, $dto->totalInvoices);
        self::assertSame(5, $dto->draftCount);
        self::assertSame(20, $dto->issuedCount);
        self::assertSame(120, $dto->paidCount);
        self::assertSame(3, $dto->cancelledCount);
        self::assertSame(2, $dto->creditedCount);
        self::assertSame(8, $dto->overdueCount);
    }

    #[Test]
    public function invoiceStatisticsDtoExposesFinancialAmounts(): void
    {
        $dto = new InvoiceStatisticsDto(
            totalInvoices: 10,
            draftCount: 0,
            issuedCount: 3,
            paidCount: 7,
            cancelledCount: 0,
            creditedCount: 0,
            overdueCount: 1,
            totalRevenueAmount: 700_000,
            totalRevenueCurrency: 'EUR',
            outstandingAmount: 300_000,
            outstandingCurrency: 'EUR',
        );

        self::assertSame(700_000, $dto->totalRevenueAmount);
        self::assertSame('EUR', $dto->totalRevenueCurrency);
        self::assertSame(300_000, $dto->outstandingAmount);
        self::assertSame('EUR', $dto->outstandingCurrency);
    }

    #[Test]
    public function invoiceStatisticsDtoSupportsZeroState(): void
    {
        $dto = new InvoiceStatisticsDto(
            totalInvoices: 0,
            draftCount: 0,
            issuedCount: 0,
            paidCount: 0,
            cancelledCount: 0,
            creditedCount: 0,
            overdueCount: 0,
            totalRevenueAmount: 0,
            totalRevenueCurrency: 'USD',
            outstandingAmount: 0,
            outstandingCurrency: 'USD',
        );

        self::assertSame(0, $dto->totalInvoices);
        self::assertSame(0, $dto->totalRevenueAmount);
        self::assertSame(0, $dto->outstandingAmount);
    }

    #[Test]
    public function invoiceStatisticsDtoAcceptsAllZeroCounts(): void
    {
        $dto = new InvoiceStatisticsDto(0, 0, 0, 0, 0, 0, 0, 0, 'USD', 0, 'USD');

        self::assertSame(0, $dto->totalInvoices);
        self::assertSame('USD', $dto->totalRevenueCurrency);
    }

    #[Test]
    public function invoiceStatisticsDtoSupportsDifferentCurrenciesForRevenueAndOutstanding(): void
    {
        $dto = new InvoiceStatisticsDto(
            totalInvoices: 5,
            draftCount: 0,
            issuedCount: 2,
            paidCount: 3,
            cancelledCount: 0,
            creditedCount: 0,
            overdueCount: 0,
            totalRevenueAmount: 30_000,
            totalRevenueCurrency: 'GBP',
            outstandingAmount: 20_000,
            outstandingCurrency: 'EUR',
        );

        self::assertSame('GBP', $dto->totalRevenueCurrency);
        self::assertSame('EUR', $dto->outstandingCurrency);
    }
}
