<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http\EventListener;

use App\Shared\Infrastructure\Http\EventListener\CorsPreflightListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(CorsPreflightListener::class)]
final class CorsPreflightListenerTest extends TestCase
{
    private CorsPreflightListener $listener;

    protected function setUp(): void
    {
        $this->listener = new CorsPreflightListener();
    }

    #[Test]
    public function itSubscribesToKernelRequest(): void
    {
        $events = CorsPreflightListener::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::REQUEST, $events);
    }

    #[Test]
    public function itReturns200ForOptionsRequestOnApiRoute(): void
    {
        $request = Request::create('/api/products', 'OPTIONS');
        $request->headers->set('Origin', 'http://localhost:3004');
        $event = $this->createRequestEvent($request);

        $this->listener->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        self::assertSame(Response::HTTP_OK, $event->getResponse()->getStatusCode());
    }

    #[Test]
    public function itDoesNotHandleNonOptionsRequests(): void
    {
        $request = Request::create('/api/products', 'GET');
        $event = $this->createRequestEvent($request);

        $this->listener->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function itDoesNotHandleOptionsOnNonApiRoutes(): void
    {
        $request = Request::create('/some-page', 'OPTIONS');
        $event = $this->createRequestEvent($request);

        $this->listener->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function itAddsCorsHeadersForAllowedOrigin(): void
    {
        $request = Request::create('/api/products', 'OPTIONS');
        $request->headers->set('Origin', 'http://localhost:3004');
        $event = $this->createRequestEvent($request);

        $this->listener->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertSame('http://localhost:3004', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertNotNull($response->headers->get('Access-Control-Allow-Methods'));
        self::assertNotNull($response->headers->get('Access-Control-Allow-Headers'));
    }

    #[Test]
    public function itDoesNotAddCorsHeadersForUnallowedOrigin(): void
    {
        $request = Request::create('/api/products', 'OPTIONS');
        $request->headers->set('Origin', 'http://evil.example.com');
        $event = $this->createRequestEvent($request);

        $this->listener->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function itHandlesAllowedLocalhostOrigins(): void
    {
        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:3001',
            'http://localhost:3004',
            'http://localhost:3005',
        ];

        foreach ($allowedOrigins as $origin) {
            $request = Request::create('/api/products', 'OPTIONS');
            $request->headers->set('Origin', $origin);
            $event = $this->createRequestEvent($request);

            $this->listener->onKernelRequest($event);

            self::assertSame(
                $origin,
                $event->getResponse()->headers->get('Access-Control-Allow-Origin'),
                "Origin $origin should be allowed"
            );
        }
    }

    // -----------------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------------

    private function createRequestEvent(Request $request): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
