<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Presentation\Api\Controller;

use App\Pricing\Application\DTO\ImportResult;
use App\Pricing\Application\DTO\ImportRow;
use App\Pricing\Application\Service\PricingImportService;
use App\Pricing\Presentation\Api\Controller\ImportPromotionsController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[\PHPUnit\Framework\Attributes\CoversClass(ImportPromotionsController::class)]
final class ImportPromotionsControllerTest extends TestCase
{
    private PricingImportService $importService;
    private MessageBusInterface $messageBus;
    private string $tenantId = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->importService = $this->createMock(PricingImportService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
    }

    private function buildController(): ImportPromotionsController
    {
        return new ImportPromotionsController($this->importService, $this->messageBus);
    }

    private function buildRequest(?string $tenantId, ?UploadedFile $file = null): Request
    {
        $request = Request::create('/api/v1/promotions/import', 'POST');
        if (null !== $tenantId) {
            $request->headers->set('X-Tenant-ID', $tenantId);
        }
        if (null !== $file) {
            $request->files->set('file', $file);
        }

        return $request;
    }

    private function buildMockFile(): UploadedFile
    {
        return $this->createMock(UploadedFile::class);
    }

    private function buildImportResult(
        int $total = 2,
        int $success = 2,
        int $errors = 0,
        int $created = 2,
        int $updated = 0,
    ): ImportResult {
        return new ImportResult(
            totalRows: $total,
            successCount: $success,
            errorCount: $errors,
            errors: [],
            createdCount: $created,
            updatedCount: $updated,
        );
    }

    private function makeImportRows(int $count = 2): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; ++$i) {
            $rows[] = new ImportRow($i, ['name' => "Promo $i"]);
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Missing tenant header
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns400WhenTenantHeaderMissing(): void
    {
        $request = $this->buildRequest(null);
        $controller = $this->buildController();
        $response = $controller->import($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('X-Tenant-ID', $response->getContent());
    }

    // ---------------------------------------------------------------
    // Missing file
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns400WhenNoFileUploaded(): void
    {
        $request = $this->buildRequest($this->tenantId);
        $controller = $this->buildController();
        $response = $controller->import($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('File upload', $data['error']);
    }

    // ---------------------------------------------------------------
    // Empty file
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns400WhenFileParsesEmpty(): void
    {
        $file = $this->buildMockFile();
        $this->importService->method('parseFile')->willReturn([]);

        $request = $this->buildRequest($this->tenantId, $file);
        $controller = $this->buildController();
        $response = $controller->import($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('empty', $data['error']);
    }

    // ---------------------------------------------------------------
    // Validation errors
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns422WhenValidationFails(): void
    {
        $rows = $this->makeImportRows(1);
        $file = $this->buildMockFile();
        $this->importService->method('parseFile')->willReturn($rows);
        $this->importService->method('validatePromotionRows')->willReturn([1 => ['name is required']]);

        $request = $this->buildRequest($this->tenantId, $file);
        $controller = $this->buildController();
        $response = $controller->import($request);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('validation_errors', $data);
    }

    // ---------------------------------------------------------------
    // Async threshold
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns202WhenImportShouldBeAsync(): void
    {
        $rows = $this->makeImportRows(3);
        $file = $this->buildMockFile();
        $this->importService->method('parseFile')->willReturn($rows);
        $this->importService->method('validatePromotionRows')->willReturn([]);
        $this->importService->method('shouldProcessAsync')->willReturn(true);

        $request = $this->buildRequest($this->tenantId, $file);
        $controller = $this->buildController();
        $response = $controller->import($request);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('asynchronous', $data['message']);
        self::assertSame(3, $data['total_rows']);
    }

    // ---------------------------------------------------------------
    // Synchronous success – HTTP 200
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns200OnSuccessfulSynchronousImport(): void
    {
        $rows = $this->makeImportRows(2);
        $file = $this->buildMockFile();
        $result = $this->buildImportResult();

        $this->importService->method('parseFile')->willReturn($rows);
        $this->importService->method('validatePromotionRows')->willReturn([]);
        $this->importService->method('shouldProcessAsync')->willReturn(false);

        $stamp = new HandledStamp($result, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $request = $this->buildRequest($this->tenantId, $file);
        $controller = $this->buildController();
        $response = $controller->import($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame(2, $data['total_rows']);
        self::assertSame(2, $data['success_count']);
        self::assertTrue($data['is_successful']);
    }

    // ---------------------------------------------------------------
    // Synchronous partial success – HTTP 206
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns206OnPartialSuccess(): void
    {
        $rows = $this->makeImportRows(2);
        $file = $this->buildMockFile();
        $result = new ImportResult(2, 1, 1, [2 => 'Row 2 failed'], 1, 0);

        $this->importService->method('parseFile')->willReturn($rows);
        $this->importService->method('validatePromotionRows')->willReturn([]);
        $this->importService->method('shouldProcessAsync')->willReturn(false);

        $stamp = new HandledStamp($result, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $request = $this->buildRequest($this->tenantId, $file);
        $controller = $this->buildController();
        $response = $controller->import($request);

        self::assertSame(Response::HTTP_PARTIAL_CONTENT, $response->getStatusCode());
    }

    // ---------------------------------------------------------------
    // Command not handled → RuntimeException → 500
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns500WhenCommandNotHandled(): void
    {
        $rows = $this->makeImportRows(1);
        $file = $this->buildMockFile();

        $this->importService->method('parseFile')->willReturn($rows);
        $this->importService->method('validatePromotionRows')->willReturn([]);
        $this->importService->method('shouldProcessAsync')->willReturn(false);

        // Envelope with no HandledStamp
        $envelope = new Envelope(new \stdClass());
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $request = $this->buildRequest($this->tenantId, $file);
        $controller = $this->buildController();
        $response = $controller->import($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    // ---------------------------------------------------------------
    // InvalidArgumentException → 400
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns400WhenInvalidArgumentExceptionThrown(): void
    {
        $file = $this->buildMockFile();
        $this->importService->method('parseFile')->willThrowException(
            new \InvalidArgumentException('Only CSV and JSON are supported')
        );

        $request = $this->buildRequest($this->tenantId, $file);
        $controller = $this->buildController();
        $response = $controller->import($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('CSV', $data['error']);
    }

    // ---------------------------------------------------------------
    // Generic Exception → 500
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns500OnGenericException(): void
    {
        $file = $this->buildMockFile();
        $this->importService->method('parseFile')->willThrowException(
            new \Exception('Connection error')
        );

        $request = $this->buildRequest($this->tenantId, $file);
        $controller = $this->buildController();
        $response = $controller->import($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('Internal server error', $data['error']);
    }
}
