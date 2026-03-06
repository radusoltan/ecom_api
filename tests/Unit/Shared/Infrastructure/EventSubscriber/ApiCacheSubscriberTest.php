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
    public function itSetsCategoriesTtlTo600(): void
    {
        $request = Request::create('/api/categories', 'GET');
        $response = new Response('{}', 200);
        $event = $this->createMainRequestEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertSame('600', $response->headers->get('X-Cache-TTL'));
    }

    #[Test]
    public function itAddsVaryHeader(): void
    {
        $request = Request::create('/api/products', 'GET');
        $response = new Response('{}', 200);
        $event = $this->createMainRequestEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertStringContainsString('Accept-Language', (string) $response->headers->get('Vary'));
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
