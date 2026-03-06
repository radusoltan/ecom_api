<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Controller;

use App\Customer\Application\DTO\ExportResult;
use App\Customer\Application\Service\CustomerExportService;
use App\Customer\Presentation\Api\Controller\CustomerExportController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(CustomerExportController::class)]
final class CustomerExportControllerTest extends TestCase
{
    private MessageBusInterface $messageBus;
    private CustomerExportService $exportService;
    private string $tenantId = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->exportService = $this->createMock(CustomerExportService::class);
    }

    private function buildController(): CustomerExportController
    {
        return new CustomerExportController($this->messageBus, $this->exportService);
    }

    // ---------------------------------------------------------------
    // export
    // ---------------------------------------------------------------

    #[Test]
    public function exportReturns400WhenTenantHeaderMissing(): void
    {
        $request = Request::create('/api/v1/customers/export', 'GET');
        $response = $this->buildController()->export($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('X-Tenant-ID', $response->getContent());
    }

    #[Test]
    public function exportReturnsCsvDownload(): void
    {
        $request = Request::create('/api/v1/customers/export?format=csv', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $exportResult = new ExportResult(
            content: "id,email\n1,john@example.com",
            filename: 'customers-export.csv',
            mimeType: 'text/csv',
        );

        $stamp = new HandledStamp($exportResult, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $response = $this->buildController()->export($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('text/csv', $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function exportReturnsJsonDownload(): void
    {
        $request = Request::create('/api/v1/customers/export?format=json', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $exportResult = new ExportResult(
            content: '[{"email":"john@example.com"}]',
            filename: 'customers-export.json',
            mimeType: 'application/json',
        );

        $stamp = new HandledStamp($exportResult, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $response = $this->buildController()->export($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function exportDefaultsToCsv(): void
    {
        $request = Request::create('/api/v1/customers/export', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $exportResult = new ExportResult('csv-content', 'export.csv', 'text/csv');
        $stamp = new HandledStamp($exportResult, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $response = $this->buildController()->export($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function exportPassesFiltersToQuery(): void
    {
        $request = Request::create('/api/v1/customers/export?format=csv&segment=vip&status=active&date_from=2025-01-01&date_to=2025-12-31', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $exportResult = new ExportResult('csv-content', 'export.csv', 'text/csv');
        $stamp = new HandledStamp($exportResult, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $response = $this->buildController()->export($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function exportReturns500WhenQueryNotHandled(): void
    {
        $request = Request::create('/api/v1/customers/export?format=csv', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $envelope = new Envelope(new \stdClass());
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $response = $this->buildController()->export($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    #[Test]
    public function exportReturns400OnInvalidArgumentException(): void
    {
        $request = Request::create('/api/v1/customers/export?format=csv', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $this->messageBus->method('dispatch')
            ->willThrowException(new \InvalidArgumentException('Invalid filter'));

        $response = $this->buildController()->export($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('Invalid request', $response->getContent());
    }

    #[Test]
    public function exportReturns500OnGenericException(): void
    {
        $request = Request::create('/api/v1/customers/export?format=csv', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $this->messageBus->method('dispatch')
            ->willThrowException(new \RuntimeException('DB error'));

        $response = $this->buildController()->export($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertStringContainsString('Export failed', $response->getContent());
    }

    // ---------------------------------------------------------------
    // template
    // ---------------------------------------------------------------

    #[Test]
    public function templateReturnsCsvTemplate(): void
    {
        $csvContent = "email,first_name,last_name\njohn@example.com,John,Doe";
        $this->exportService->method('getCsvTemplate')->willReturn($csvContent);

        $response = $this->buildController()->template();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        self::assertStringContainsString('customers-import-template.csv', $response->headers->get('Content-Disposition'));
        self::assertSame($csvContent, $response->getContent());
    }

    #[Test]
    public function templateReturns500OnException(): void
    {
        $this->exportService->method('getCsvTemplate')
            ->willThrowException(new \RuntimeException('Failed'));

        $response = $this->buildController()->template();

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertStringContainsString('Template generation failed', $response->getContent());
    }
}
