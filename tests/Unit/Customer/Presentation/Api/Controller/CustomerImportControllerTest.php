<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Controller;

use App\Customer\Application\DTO\ImportResult;
use App\Customer\Presentation\Api\Controller\CustomerImportController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(CustomerImportController::class)]
final class CustomerImportControllerTest extends TestCase
{
    private MessageBusInterface $messageBus;
    private string $tenantId = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
    }

    private function buildController(): CustomerImportController
    {
        return new CustomerImportController($this->messageBus);
    }

    private function buildRequest(?string $tenantId, ?UploadedFile $file = null, string $updateExisting = 'false'): Request
    {
        $request = Request::create('/api/v1/customers/import', 'POST');
        if (null !== $tenantId) {
            $request->headers->set('X-Tenant-ID', $tenantId);
        }
        if (null !== $file) {
            $request->files->set('file', $file);
        }
        $request->request->set('update_existing', $updateExisting);

        return $request;
    }

    private function buildMockFile(string $extension = 'csv', bool $valid = true): UploadedFile
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn($extension);
        $file->method('isValid')->willReturn($valid);
        $file->method('getPathname')->willReturn('/tmp/test-file.'.$extension);
        $file->method('getErrorMessage')->willReturn('Upload error');

        return $file;
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
    public function itReturns400WhenFileIsInvalid(): void
    {
        $file = $this->buildMockFile('csv', false);
        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('Invalid file upload', $data['error']);
    }

    #[Test]
    public function itReturns400WhenUnsupportedFormat(): void
    {
        $file = $this->buildMockFile('xml');
        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('Unsupported file format', $data['error']);
    }

    #[Test]
    public function itReturns500WhenFileContentCannotBeRead(): void
    {
        // We need a real temp file that we can then remove to simulate read failure.
        // Instead we test by using a file path that doesn't exist. file_get_contents returns false.
        $file = $this->buildMockFile('csv');
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('csv');
        $file->method('isValid')->willReturn(true);
        $file->method('getPathname')->willReturn('/tmp/nonexistent-file-'.uniqid());

        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        // file_get_contents returns false for non-existent file, triggering error response
        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    #[Test]
    public function itReturns200OnSuccessfulCsvImport(): void
    {
        // Create a real temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, "email,first_name\njohn@example.com,John");

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('csv');
        $file->method('isValid')->willReturn(true);
        $file->method('getPathname')->willReturn($tmpFile);

        $result = new ImportResult(
            totalRows: 1,
            imported: 1,
            updated: 0,
            skipped: 0,
            errors: [],
        );

        $stamp = new HandledStamp($result, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame(1, $data['total_rows']);
        self::assertTrue($data['is_successful']);

        @unlink($tmpFile);
    }

    #[Test]
    public function itReturns200OnSuccessfulJsonImport(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, '[{"email":"john@example.com"}]');

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('json');
        $file->method('isValid')->willReturn(true);
        $file->method('getPathname')->willReturn($tmpFile);

        $result = new ImportResult(1, 1, 0, 0, []);

        $stamp = new HandledStamp($result, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        @unlink($tmpFile);
    }

    #[Test]
    public function itReturns206OnPartialSuccess(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, "email,first_name\njohn@example.com,John");

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('csv');
        $file->method('isValid')->willReturn(true);
        $file->method('getPathname')->willReturn($tmpFile);

        $result = new ImportResult(2, 1, 0, 0, [2 => 'Row 2 invalid']);

        $stamp = new HandledStamp($result, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_PARTIAL_CONTENT, $response->getStatusCode());

        @unlink($tmpFile);
    }

    #[Test]
    public function itReturns500WhenCommandNotHandled(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, "email\njohn@example.com");

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('csv');
        $file->method('isValid')->willReturn(true);
        $file->method('getPathname')->willReturn($tmpFile);

        $envelope = new Envelope(new \stdClass());
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        @unlink($tmpFile);
    }

    #[Test]
    public function itReturns400OnInvalidArgumentException(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'data');

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('csv');
        $file->method('isValid')->willReturn(true);
        $file->method('getPathname')->willReturn($tmpFile);

        $this->messageBus->method('dispatch')
            ->willThrowException(new \InvalidArgumentException('Bad data'));

        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('Invalid request', $data['error']);

        @unlink($tmpFile);
    }

    #[Test]
    public function itReturns500OnGenericException(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'data');

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('csv');
        $file->method('isValid')->willReturn(true);
        $file->method('getPathname')->willReturn($tmpFile);

        $this->messageBus->method('dispatch')
            ->willThrowException(new \RuntimeException('DB error'));

        $request = $this->buildRequest($this->tenantId, $file);
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('Import failed', $data['error']);

        @unlink($tmpFile);
    }

    #[Test]
    public function itHandlesUpdateExistingFlag(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, "email\njohn@example.com");

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('csv');
        $file->method('isValid')->willReturn(true);
        $file->method('getPathname')->willReturn($tmpFile);

        $result = new ImportResult(1, 0, 1, 0, []);

        $stamp = new HandledStamp($result, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $request = $this->buildRequest($this->tenantId, $file, 'true');
        $response = $this->buildController()->import($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        @unlink($tmpFile);
    }
}
