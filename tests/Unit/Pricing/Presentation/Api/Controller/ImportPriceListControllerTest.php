<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Presentation\Api\Controller;

use App\Pricing\Application\DTO\ImportResult;
use App\Pricing\Application\DTO\ImportRow;
use App\Pricing\Application\Service\PricingImportService;
use App\Pricing\Presentation\Api\Controller\ImportPriceListController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ImportPriceListController::class)]
final class ImportPriceListControllerTest extends TestCase
{
    private PricingImportService $importService;
    private MessageBusInterface $messageBus;
    private string $tenantId = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->importService = $this->createMock(PricingImportService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
    }

    private function buildController(): ImportPriceListController
    {
        return new ImportPriceListController($this->importService, $this->messageBus);
    }

    private function buildRequest(?string $tenantId, ?UploadedFile $file = null): Request
    {
        $request = Request::create('/api/v1/price-lists/import', 'POST');
        if (null !== $tenantId) {
            $request->headers->set('X-Tenant-ID', $tenantId);
        }
        if (null !== $file) {
            $request->files->set('file', $file);
        }

        return $request;
    }

    private function makeImportRows(int $count = 2): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; ++$i) {
            $rows[] = new ImportRow($i, ['name' => "PriceList $i"]);
        }

        return $rows;
    }

    #[Test]
    public function itReturns400WhenTenantHeaderMissing(): void
    {
        $request = $this->buildRequest(null);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('X-Tenant-ID', $data['error']);
    }

    #[Test]
    public function itReturns400WhenNoFileUploaded(): void
    {
        $request = $this->buildRequest($this->tenantId);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('File upload', $data['error']);
    }

    #[Test]
    public function itReturns400WhenFileParsesEmpty(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $this->importService->method('parseFile')->willReturn([]);

        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('empty', $data['error']);
    }

    #[Test]
    public function itReturns422WhenValidationFails(): void
    {
        $rows = $this->makeImportRows(1);
        $file = $this->createMock(UploadedFile::class);
        $this->importService->method('parseFile')->willReturn($rows);
        $this->importService->method('validatePriceListRows')->willReturn([1 => ['price is required']]);

        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('validation_errors', $data);
    }

    #[Test]
    public function itReturns202WhenImportShouldBeAsync(): void
    {
        $rows = $this->makeImportRows(3);
        $file = $this->createMock(UploadedFile::class);
        $this->importService->method('parseFile')->willReturn($rows);
        $this->importService->method('validatePriceListRows')->willReturn([]);
        $this->importService->method('shouldProcessAsync')->willReturn(true);

        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('asynchronous', $data['message']);
        self::assertSame(3, $data['total_rows']);
    }

    #[Test]
    public function itReturns200OnSuccessfulSynchronousImport(): void
    {
        $rows = $this->makeImportRows(2);
        $file = $this->createMock(UploadedFile::class);
        $result = new ImportResult(
            totalRows: 2,
            successCount: 2,
            errorCount: 0,
            errors: [],
            createdCount: 2,
            updatedCount: 0,
        );

        $this->importService->method('parseFile')->willReturn($rows);
        $this->importService->method('validatePriceListRows')->willReturn([]);
        $this->importService->method('shouldProcessAsync')->willReturn(false);

        $stamp = new HandledStamp($result, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame(2, $data['total_rows']);
        self::assertTrue($data['is_successful']);
    }

    #[Test]
    public function itReturns206OnPartialSuccess(): void
    {
        $rows = $this->makeImportRows(2);
        $file = $this->createMock(UploadedFile::class);
        $result = new ImportResult(2, 1, 1, [2 => 'Row 2 failed'], 1, 0);

        $this->importService->method('parseFile')->willReturn($rows);
        $this->importService->method('validatePriceListRows')->willReturn([]);
        $this->importService->method('shouldProcessAsync')->willReturn(false);

        $stamp = new HandledStamp($result, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_PARTIAL_CONTENT, $response->getStatusCode());
    }

    #[Test]
    public function itReturns500WhenCommandNotHandled(): void
    {
        $rows = $this->makeImportRows(1);
        $file = $this->createMock(UploadedFile::class);

        $this->importService->method('parseFile')->willReturn($rows);
        $this->importService->method('validatePriceListRows')->willReturn([]);
        $this->importService->method('shouldProcessAsync')->willReturn(false);

        $envelope = new Envelope(new \stdClass());
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    #[Test]
    public function itReturns400OnInvalidArgumentException(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $this->importService->method('parseFile')
            ->willThrowException(new \InvalidArgumentException('Only CSV and JSON'));

        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function itReturns500OnGenericException(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $this->importService->method('parseFile')
            ->willThrowException(new \RuntimeException('Connection error'));

        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('Internal server error', $data['error']);
    }
}
