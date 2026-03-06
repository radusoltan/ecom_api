<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\EventSubscriber;

use App\Shared\Infrastructure\EventSubscriber\RateLimitHeadersSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(RateLimitHeadersSubscriber::class)]
final class RateLimitHeadersSubscriberTest extends TestCase
{
    private RateLimitHeadersSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new RateLimitHeadersSubscriber();
    }

    #[Test]
    public function itSubscribesToKernelResponse(): void
    {
        $events = RateLimitHeadersSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    #[Test]
    public function itAddsRateLimitHeadersWhenAttributesPresent(): void
    {
        $request = new Request();
        $request->attributes->set('rate_limit_remaining', 95);
        $request->attributes->set('rate_limit_limit', 100);
        $request->attributes->set('rate_limit_reset', 1700000000);

        $response = new Response();
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertSame('100', $response->headers->get('X-RateLimit-Limit'));
        self::assertSame('95', $response->headers->get('X-RateLimit-Remaining'));
        self::assertSame('1700000000', $response->headers->get('X-RateLimit-Reset'));
    }

    #[Test]
    public function itDoesNotAddHeadersWhenAttributeAbsent(): void
    {
        $request = new Request(); // no rate_limit_remaining attribute
        $response = new Response();
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertNull($response->headers->get('X-RateLimit-Limit'));
        self::assertNull($response->headers->get('X-RateLimit-Remaining'));
        self::assertNull($response->headers->get('X-RateLimit-Reset'));
    }

    #[Test]
    public function itAddsZeroRemainingWhenRateLimitHit(): void
    {
        $request = new Request();
        $request->attributes->set('rate_limit_remaining', 0);
        $request->attributes->set('rate_limit_limit', 100);
        $request->attributes->set('rate_limit_reset', 1700000000);

        $response = new Response();
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertSame('0', $response->headers->get('X-RateLimit-Remaining'));
    }

    // -----------------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------------

    private function createResponseEvent(Request $request, Response $response): ResponseEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
