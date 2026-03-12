<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\EventSubscriber;

use App\Shared\Infrastructure\EventSubscriber\ApiCacheSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(ApiCacheSubscriber::class)]
final class ApiCacheSubscriberTest extends TestCase
{
    private ApiCacheSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new ApiCacheSubscriber();
    }

    #[Test]
    public function itSubscribesToKernelResponse(): void
    {
        $events = ApiCacheSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    #[Test]
    public function itSetsCacheHeadersForPublicGetApiRequest(): void
    {
        $request = Request::create('/api/products', 'GET');
        $response = new Response('{"data":"value"}', 200);
        $event = $this->createMainRequestEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('stale-while-revalidate=600', (string) $response->headers->get('Cache-Control'));
        self::assertNotNull($response->headers->get('ETag'));
        self::assertNotNull($response->headers->get('X-Cache-TTL'));
    }

    #[Test]
    public function itDoesNotCacheNonApiRoutes(): void
    {
        $request = Request::create('/some-page', 'GET');
        $response = new Response('page content', 200);
        $event = $this->createMainRequestEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertNull($response->headers->get('X-Cache-TTL'));
    }

    #[Test]
    public function itDoesNotCachePostRequests(): void
    {
        $request = Request::create('/api/products', 'POST');
        $response = new Response('{}', 201);
        $event = $this->createMainRequestEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertNull($response->headers->get('X-Cache-TTL'));
    }

    #[Test]
    public function itDoesNotCacheErrorResponses(): void
    {
        $request = Request::create('/api/products', 'GET');
        $response = new Response('not found', 404);
        $event = $this->createMainRequestEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertNull($response->headers->get('X-Cache-TTL'));
    }

    #[Test]
    public function itSetsCacheNoCacheWhenXNoCacheHeaderPresent(): void
    {
        $request = Request::create('/api/products', 'GET');
        $request->headers->set('X-No-Cache', 'true');
        $response = new Response('{}', 200);
        $event = $this->createMainRequestEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function itSetsNoCacheForOrdersRoute(): void
    {
        $request = Request::create('/api/orders', 'GET');
        $response = new Response('{}', 200);
        $event = $this->createMainRequestEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function itSetsNoCacheForPaymentsRoute(): void
    {
        $request = Request::create('/api/payments', 'GET');
        $response = new Response('{}', 200);
        $event = $this->createMainRequestEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function itSetsProductsTtlTo300(): void
    {
        $request = Request::create('/api/products', 'GET');
        $response = new Response('{}', 200);
        $event = $this->createMainRequestEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertSame('300', $response->headers->get('X-Cache-TTL'));
    }

    #[Test]
    public function itSetsCategoriesTtlTo300(): void
    {
        $request = Request::create('/api/categories', 'GET');
        $response = new Response('{}', 200);
        $event = $this->createMainRequestEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertSame('300', $response->headers->get('X-Cache-TTL'));
    }

    #[Test]
    public function itAddsVaryHeader(): void
    {
        $request = Request::create('/api/products', 'GET');
        $response = new Response('{}', 200);
        $event = $this->createMainRequestEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        $vary = implode(', ', $response->headers->all('Vary'));

        self::assertStringContainsString('Accept', $vary);
        self::assertStringContainsString('Accept-Language', $vary);
        self::assertStringContainsString('X-Tenant-ID', $vary);
    }

    #[Test]
    public function itSetsEtagOnResponse(): void
    {
        $content = '{"data":"products"}';

        $request = Request::create('/api/products', 'GET');
        $response = new Response($content, 200);
        $event = $this->createMainRequestEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertNotNull($response->getEtag());
    }

    #[Test]
    public function itUsesUpdatedAtBasedEtagForConditionalRequests(): void
    {
        $resource = new class {
            public function getId(): string
            {
                return 'resource-123';
            }

            public function getUpdatedAt(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-03-10T12:00:00+00:00');
            }
        };

        $firstRequest = Request::create('/api/products/resource-123', 'GET');
        $firstRequest->attributes->set('data', $resource);
        $firstResponse = new Response('{"data":"products"}', 200);
        $firstEvent = $this->createMainRequestEvent($firstRequest, $firstResponse);

        $this->subscriber->onKernelResponse($firstEvent);

        $etag = $firstResponse->getEtag();

        self::assertNotNull($etag);
        self::assertNotNull($firstResponse->headers->get('Last-Modified'));

        $conditionalRequest = Request::create('/api/products/resource-123', 'GET');
        $conditionalRequest->headers->set('If-None-Match', $etag);
        $conditionalRequest->attributes->set('data', $resource);
        $conditionalResponse = new Response('{"data":"products"}', 200);
        $conditionalEvent = $this->createMainRequestEvent($conditionalRequest, $conditionalResponse);

        $this->subscriber->onKernelResponse($conditionalEvent);

        self::assertSame(304, $conditionalResponse->getStatusCode());
    }

    #[Test]
    public function itDoesNotProcessSubRequests(): void
    {
        $request = Request::create('/api/products', 'GET');
        $response = new Response('{}', 200);
        $kernel = $this->createMock(HttpKernelInterface::class);
        // SUB_REQUEST instead of MAIN_REQUEST
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertNull($response->headers->get('X-Cache-TTL'));
    }

    // -----------------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------------

    private function createMainRequestEvent(Request $request, Response $response): ResponseEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
