<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http\Middleware;

use App\Shared\Infrastructure\Http\Middleware\IdempotencyMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class IdempotencyMiddlewareTest extends TestCase
{
    private CacheInterface $cache;
    private LoggerInterface $logger;
    private IdempotencyMiddleware $middleware;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->middleware = new IdempotencyMiddleware($this->cache, $this->logger);
    }

    public function testIgnoresNonPostRequests(): void
    {
        $request = Request::create('/api/orders', 'GET');
        $request->headers->set('Idempotency-Key', 'test-key-123');

        $event = $this->createRequestEvent($request);

        $this->cache->expects($this->never())->method('get');

        $this->middleware->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testIgnoresPostRequestsWithoutIdempotencyKey(): void
    {
        $request = Request::create('/api/orders', 'POST', [], [], [], [], '{"test": "data"}');

        $event = $this->createRequestEvent($request);

        $this->cache->expects($this->never())->method('get');

        $this->middleware->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testAllowsFirstRequestWithValidKey(): void
    {
        $request = Request::create('/api/orders', 'POST', [], [], [], [], '{"test": "data"}');
        $request->headers->set('Idempotency-Key', 'test-key-123');
        $request->headers->set('X-Tenant-ID', 'tenant-123');

        $event = $this->createRequestEvent($request);

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                return $callback($this->createMock(ItemInterface::class));
            });

        $this->middleware->onKernelRequest($event);

        $this->assertNull($event->getResponse());
        $this->assertEquals('test-key-123', $request->attributes->get('_idempotency_key'));
        $this->assertNotNull($request->attributes->get('_idempotency_request_hash'));
    }

    public function testReturnsCachedResponseForDuplicateRequest(): void
    {
        $requestPayload = '{"test": "data"}';
        $request = Request::create('/api/orders', 'POST', [], [], [], [], $requestPayload);
        $request->headers->set('Idempotency-Key', 'test-key-123');
        $request->headers->set('X-Tenant-ID', 'tenant-123');

        $cachedData = [
            'status_code' => 201,
            'headers' => ['Content-Type' => ['application/json']],
            'content' => '{"id": "order-123"}',
            'request_hash' => hash('sha256', $requestPayload),
            'cached_at' => '2025-01-01T00:00:00+00:00',
        ];

        $event = $this->createRequestEvent($request);

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) use ($cachedData) {
                return $cachedData;
            });

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Idempotency: reused cached response', $this->anything());

        $this->middleware->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('{"id": "order-123"}', $response->getContent());
        $this->assertEquals('true', $response->headers->get('X-Idempotency-Replay'));
    }

    public function testReturns422ForDifferentPayloadWithSameKey(): void
    {
        $request = Request::create('/api/orders', 'POST', [], [], [], [], '{"different": "payload"}');
        $request->headers->set('Idempotency-Key', 'test-key-123');
        $request->headers->set('X-Tenant-ID', 'tenant-123');

        $cachedData = [
            'status_code' => 201,
            'headers' => ['Content-Type' => ['application/json']],
            'content' => '{"id": "order-123"}',
            'request_hash' => hash('sha256', '{"original": "payload"}'),
            'cached_at' => '2025-01-01T00:00:00+00:00',
        ];

        $event = $this->createRequestEvent($request);

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn($cachedData);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Idempotency key reused with different payload', $this->anything());

        $this->middleware->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertStringContainsString('Idempotency key conflict', $response->getContent());
    }

    public function testRejectsInvalidIdempotencyKeyFormat(): void
    {
        $request = Request::create('/api/orders', 'POST', [], [], [], [], '{"test": "data"}');
        $request->headers->set('Idempotency-Key', 'invalid key with spaces!');

        $event = $this->createRequestEvent($request);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Invalid idempotency key format', $this->anything());

        $this->cache->expects($this->never())->method('get');

        $this->middleware->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testCachesSuccessfulResponse(): void
    {
        $request = Request::create('/api/orders', 'POST');
        $request->attributes->set('_idempotency_key', 'test-key-123');
        $request->attributes->set('_idempotency_tenant_id', 'tenant-123');
        $request->attributes->set('_idempotency_request_hash', hash('sha256', '{"test": "data"}'));

        $response = new Response('{"id": "order-123"}', 201, ['Content-Type' => 'application/json']);

        $event = $this->createResponseEvent($request, $response);

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())
                    ->method('expiresAfter')
                    ->with(86400);
                return $callback($item);
            });

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Idempotency: cached response', $this->anything());

        $this->middleware->onKernelResponse($event);
    }

    public function testDoesNotCacheErrorResponses(): void
    {
        $request = Request::create('/api/orders', 'POST');
        $request->attributes->set('_idempotency_key', 'test-key-123');
        $request->attributes->set('_idempotency_tenant_id', 'tenant-123');
        $request->attributes->set('_idempotency_request_hash', hash('sha256', '{"test": "data"}'));

        $response = new Response('{"error": "Bad request"}', 400);

        $event = $this->createResponseEvent($request, $response);

        $this->cache->expects($this->never())->method('get');

        $this->middleware->onKernelResponse($event);
    }

    public function testHandlesCacheExceptionGracefully(): void
    {
        $request = Request::create('/api/orders', 'POST', [], [], [], [], '{"test": "data"}');
        $request->headers->set('Idempotency-Key', 'test-key-123');

        $event = $this->createRequestEvent($request);

        $this->cache->expects($this->once())
            ->method('get')
            ->willThrowException(new \Exception('Cache error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Idempotency middleware error', $this->anything());

        $this->middleware->onKernelRequest($event);

        // Request should proceed despite cache error
        $this->assertNull($event->getResponse());
    }

    private function createRequestEvent(Request $request): RequestEvent
    {
        $kernel = $this->createMock(KernelInterface::class);
        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function createResponseEvent(Request $request, Response $response): ResponseEvent
    {
        $kernel = $this->createMock(KernelInterface::class);
        return new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
