<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Infrastructure\ApiPlatform\Controller;

use App\Internationalization\Domain\Service\TranslationImportService;
use App\Internationalization\Infrastructure\ApiPlatform\Controller\TranslationToolsController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(TranslationToolsController::class)]
final class TranslationToolsControllerTest extends TestCase
{
    private MessageBusInterface $queryBus;
    private MessageBusInterface $commandBus;
    private LoggerInterface $logger;
    private string $tenantId = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function buildController(): TranslationToolsController
    {
        return new TranslationToolsController(
            $this->queryBus,
            $this->commandBus,
            $this->logger,
        );
    }

    // ---------------------------------------------------------------
    // stats
    // ---------------------------------------------------------------

    #[Test]
    public function statsReturns400WhenTenantHeaderMissing(): void
    {
        $request = Request::create('/api/v1/translation-management/stats', 'GET');
        $controller = $this->buildController();
        $response = $controller->stats($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('X-Tenant-ID', $data['error']);
    }

    #[Test]
    public function statsReturns500WhenQueryNotHandled(): void
    {
        $request = Request::create('/api/v1/translation-management/stats', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $envelope = new Envelope(new \stdClass());
        $this->queryBus->method('dispatch')->willReturn($envelope);

        $response = $this->buildController()->stats($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame('Query was not handled', $data['error']);
    }

    #[Test]
    public function statsReturnsSuccessful(): void
    {
        $request = Request::create('/api/v1/translation-management/stats', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $result = ['total' => 100, 'translated' => 80, 'untranslated' => 20];
        $stamp = new HandledStamp($result, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->queryBus->method('dispatch')->willReturn($envelope);

        $response = $this->buildController()->stats($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame(100, $data['total']);
    }

    // ---------------------------------------------------------------
    // import
    // ---------------------------------------------------------------

    #[Test]
    public function importReturns400WhenTenantHeaderMissing(): void
    {
        $request = Request::create('/api/v1/translations/import', 'POST');
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function importReturns400WhenNoFileUploaded(): void
    {
        $request = Request::create('/api/v1/translations/import', 'POST');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('No file uploaded', $data['error']);
    }

    #[Test]
    public function importReturns400WhenFormatMissing(): void
    {
        $request = Request::create('/api/v1/translations/import', 'POST');
        $request->headers->set('X-Tenant-ID', $this->tenantId);
        $file = $this->createMock(UploadedFile::class);
        $request->files->set('file', $file);

        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('format', $data['error']);
    }

    #[Test]
    public function importReturns400WhenFormatInvalid(): void
    {
        $request = Request::create('/api/v1/translations/import', 'POST');
        $request->headers->set('X-Tenant-ID', $this->tenantId);
        $file = $this->createMock(UploadedFile::class);
        $request->files->set('file', $file);
        $request->request->set('format', 'xml');

        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function importReturns500WhenCommandNotHandled(): void
    {
        $request = Request::create('/api/v1/translations/import', 'POST');
        $request->headers->set('X-Tenant-ID', $this->tenantId);
        $file = $this->createMock(UploadedFile::class);
        $request->files->set('file', $file);
        $request->request->set('format', 'csv');

        $envelope = new Envelope(new \stdClass());
        $this->commandBus->method('dispatch')->willReturn($envelope);

        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame('Command was not handled', $data['error']);
    }

    #[Test]
    public function importReturnsSuccessful(): void
    {
        $request = Request::create('/api/v1/translations/import', 'POST');
        $request->headers->set('X-Tenant-ID', $this->tenantId);
        $file = $this->createMock(UploadedFile::class);
        $request->files->set('file', $file);
        $request->request->set('format', 'json');
        $request->request->set('dryRun', 'true');

        // Force autoload of ImportResult (secondary class in TranslationImportService.php)
        class_exists(TranslationImportService::class);
        $importResult = new \App\Internationalization\Domain\Service\ImportResult();
        $importResult->incrementProcessed();
        $importResult->incrementCreated();

        $stamp = new HandledStamp($importResult, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->commandBus->method('dispatch')->willReturn($envelope);

        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame(1, $data['processed']);
        self::assertSame(1, $data['created']);
    }

    #[Test]
    public function importReturns500OnException(): void
    {
        $request = Request::create('/api/v1/translations/import', 'POST');
        $request->headers->set('X-Tenant-ID', $this->tenantId);
        $file = $this->createMock(UploadedFile::class);
        $request->files->set('file', $file);
        $request->request->set('format', 'csv');

        $this->commandBus->method('dispatch')
            ->willThrowException(new \RuntimeException('DB error'));

        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame('Import failed', $data['error']);
    }

    // ---------------------------------------------------------------
    // export
    // ---------------------------------------------------------------

    #[Test]
    public function exportReturns400WhenTenantHeaderMissing(): void
    {
        $request = Request::create('/api/v1/translations/export', 'GET');
        $response = $this->buildController()->export($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function exportReturns400WhenFormatMissing(): void
    {
        $request = Request::create('/api/v1/translations/export', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $response = $this->buildController()->export($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function exportReturns400WhenFormatInvalid(): void
    {
        $request = Request::create('/api/v1/translations/export?format=xml', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $response = $this->buildController()->export($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function exportReturns500WhenQueryNotHandled(): void
    {
        $request = Request::create('/api/v1/translations/export?format=csv', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $envelope = new Envelope(new \stdClass());
        $this->queryBus->method('dispatch')->willReturn($envelope);

        $response = $this->buildController()->export($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    #[Test]
    public function exportReturnsCsvDownload(): void
    {
        $request = Request::create('/api/v1/translations/export?format=csv&domain=messages&locale=en', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $csvContent = "key,value\nhello,world";
        $stamp = new HandledStamp($csvContent, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->queryBus->method('dispatch')->willReturn($envelope);

        $response = $this->buildController()->export($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('.csv', $response->headers->get('Content-Disposition'));
        self::assertSame($csvContent, $response->getContent());
    }

    #[Test]
    public function exportReturnsJsonDownload(): void
    {
        $request = Request::create('/api/v1/translations/export?format=json', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $jsonContent = '{"hello":"world"}';
        $stamp = new HandledStamp($jsonContent, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->queryBus->method('dispatch')->willReturn($envelope);

        $response = $this->buildController()->export($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        self::assertStringContainsString('.json', $response->headers->get('Content-Disposition'));
    }

    // ---------------------------------------------------------------
    // missing
    // ---------------------------------------------------------------

    #[Test]
    public function missingReturns400WhenTenantHeaderMissing(): void
    {
        $request = Request::create('/api/v1/translation-management/missing', 'GET');
        $response = $this->buildController()->missing($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function missingReturns400WhenTargetLocaleMissing(): void
    {
        $request = Request::create('/api/v1/translation-management/missing', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $response = $this->buildController()->missing($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('targetLocale', $data['error']);
    }

    #[Test]
    public function missingReturns500WhenQueryNotHandled(): void
    {
        $request = Request::create('/api/v1/translation-management/missing?targetLocale=fr', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $envelope = new Envelope(new \stdClass());
        $this->queryBus->method('dispatch')->willReturn($envelope);

        $response = $this->buildController()->missing($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    #[Test]
    public function missingReturnsSuccessful(): void
    {
        $request = Request::create('/api/v1/translation-management/missing?targetLocale=fr&sourceLocale=en&domain=messages', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $result = [
            ['key' => 'hello', 'domain' => 'messages'],
            ['key' => 'goodbye', 'domain' => 'messages'],
        ];
        $stamp = new HandledStamp($result, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->queryBus->method('dispatch')->willReturn($envelope);

        $response = $this->buildController()->missing($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertCount(2, $data);
        self::assertSame('hello', $data[0]['key']);
    }
}
