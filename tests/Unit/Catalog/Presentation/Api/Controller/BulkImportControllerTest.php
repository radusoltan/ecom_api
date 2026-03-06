<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Presentation\Api\Controller;

use App\Catalog\Presentation\Api\Controller\BulkImportController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(BulkImportController::class)]
final class BulkImportControllerTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private BulkImportController $controller;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->controller = new BulkImportController($this->messageBus);
    }

    #[Test]
    public function invokeReturnsBadRequestWhenTenantIdMissing(): void
    {
        $request = new Request(content: json_encode(['products' => []]));

        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        self::assertSame('X-Tenant-ID header is required', $body['error']);
    }

    #[Test]
    public function invokeReturnsBadRequestWhenTenantIdEmpty(): void
    {
        $request = new Request(
            server: ['HTTP_X_TENANT_ID' => ''],
            content: json_encode(['products' => []]),
        );

        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        self::assertSame('X-Tenant-ID header is required', $body['error']);
    }

    #[Test]
    public function invokeReturnsBadRequestWhenBodyNotArray(): void
    {
        $request = new Request(
            server: ['HTTP_X_TENANT_ID' => '00000000-0000-4000-8000-000000000001'],
            content: '"not-an-array"',
        );

        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        self::assertSame('Request body must contain a "products" array', $body['error']);
    }

    #[Test]
    public function invokeReturnsBadRequestWhenProductsKeyMissing(): void
    {
        $request = new Request(
            server: ['HTTP_X_TENANT_ID' => '00000000-0000-4000-8000-000000000001'],
            content: json_encode(['items' => []]),
        );

        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        self::assertSame('Request body must contain a "products" array', $body['error']);
    }

    #[Test]
    public function invokeReturnsBadRequestWhenProductsNotArray(): void
    {
        $request = new Request(
            server: ['HTTP_X_TENANT_ID' => '00000000-0000-4000-8000-000000000001'],
            content: json_encode(['products' => 'not-array']),
        );

        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        self::assertSame('Request body must contain a "products" array', $body['error']);
    }

    #[Test]
    public function invokeReturnsBadRequestWhenTooManyProducts(): void
    {
        $products = array_fill(0, 1001, ['name' => 'Product']);
        $request = new Request(
            server: ['HTTP_X_TENANT_ID' => '00000000-0000-4000-8000-000000000001'],
            content: json_encode(['products' => $products]),
        );

        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        self::assertSame('Maximum 1000 products per import', $body['error']);
    }

    #[Test]
    public function invokeDispatchesCommandAndReturnsResult(): void
    {
        $products = [['name' => 'Product 1'], ['name' => 'Product 2']];
        $request = new Request(
            server: ['HTTP_X_TENANT_ID' => '00000000-0000-4000-8000-000000000001'],
            content: json_encode(['products' => $products]),
        );

        $importResult = ['imported' => 2, 'failed' => 0];
        $handledStamp = new HandledStamp($importResult, 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturn($envelope);

        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        self::assertSame(2, $body['imported']);
        self::assertSame(0, $body['failed']);
    }

    #[Test]
    public function invokeReturnsErrorWhenNoHandledStamp(): void
    {
        $products = [['name' => 'Product 1']];
        $request = new Request(
            server: ['HTTP_X_TENANT_ID' => '00000000-0000-4000-8000-000000000001'],
            content: json_encode(['products' => $products]),
        );

        $envelope = new Envelope(new \stdClass());

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturn($envelope);

        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        self::assertSame('Import failed', $body['error']);
    }

    #[Test]
    public function invokeAcceptsExactly1000Products(): void
    {
        $products = array_fill(0, 1000, ['name' => 'Product']);
        $request = new Request(
            server: ['HTTP_X_TENANT_ID' => '00000000-0000-4000-8000-000000000001'],
            content: json_encode(['products' => $products]),
        );

        $handledStamp = new HandledStamp(['imported' => 1000], 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturn($envelope);

        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function invokeReturnsBadRequestWhenBodyInvalidJson(): void
    {
        $request = new Request(
            server: ['HTTP_X_TENANT_ID' => '00000000-0000-4000-8000-000000000001'],
            content: '{invalid json',
        );

        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }
}
