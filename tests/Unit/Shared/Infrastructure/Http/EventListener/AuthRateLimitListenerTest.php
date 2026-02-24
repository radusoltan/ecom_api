<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http\EventListener;

use App\Shared\Infrastructure\Http\EventListener\AuthRateLimitListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

#[CoversClass(AuthRateLimitListener::class)]
final class AuthRateLimitListenerTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeEvent(Request $request, bool $isMain = true): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $requestType = $isMain
            ? HttpKernelInterface::MAIN_REQUEST
            : HttpKernelInterface::SUB_REQUEST;

        return new RequestEvent($kernel, $request, $requestType);
    }

    private function createAcceptedRateLimit(int $limit = 10, int $remaining = 9): RateLimit
    {
        return new RateLimit(
            availableTokens: $remaining,
            retryAfter: new \DateTimeImmutable('+60 seconds'),
            accepted: true,
            limit: $limit,
        );
    }

    private function createRejectedRateLimit(int $limit = 10): RateLimit
    {
        return new RateLimit(
            availableTokens: 0,
            retryAfter: new \DateTimeImmutable('+45 seconds'),
            accepted: false,
            limit: $limit,
        );
    }

    private function createLimiterFactory(RateLimit $rateLimit): RateLimiterFactoryInterface
    {
        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->method('consume')->willReturn($rateLimit);

        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        return $factory;
    }

    // -----------------------------------------------------------------------
    // Auth endpoint detection
    // -----------------------------------------------------------------------

    /**
     * @return iterable<string, array{string}>
     */
    public static function authEndpointProvider(): iterable
    {
        yield 'login_check' => ['/api/login_check'];
        yield 'login' => ['/api/login'];
        yield 'register' => ['/api/v1/auth/register'];
        yield 'password_reset' => ['/api/v1/auth/password'];
        yield 'password_reset_confirm' => ['/api/v1/auth/password/confirm'];
    }

    #[Test]
    #[DataProvider('authEndpointProvider')]
    public function itAppliesRateLimitToAuthEndpoints(string $path): void
    {
        $factory = $this->createLimiterFactory($this->createAcceptedRateLimit());
        $listener = new AuthRateLimitListener($factory);

        $request = Request::create($path);
        $event = $this->makeEvent($request);

        ($listener)($event);

        // Should set rate limit attributes (accepted request)
        self::assertSame(10, $request->attributes->get('rate_limit_limit'));
        self::assertSame(9, $request->attributes->get('rate_limit_remaining'));
        self::assertFalse($event->hasResponse(), 'Accepted request should not set a response');
    }

    // -----------------------------------------------------------------------
    // Non-auth endpoints passthrough
    // -----------------------------------------------------------------------

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonAuthEndpointProvider(): iterable
    {
        yield 'products' => ['/api/v1/products'];
        yield 'orders' => ['/api/v1/orders'];
        yield 'tenants' => ['/api/v1/tenants'];
        yield 'mfa_verify' => ['/api/v1/mfa/verify'];
        yield 'customers' => ['/api/v1/customers'];
        yield 'search' => ['/api/v1/search/products'];
    }

    #[Test]
    #[DataProvider('nonAuthEndpointProvider')]
    public function itDoesNotApplyToNonAuthEndpoints(string $path): void
    {
        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->expects(self::never())->method('create');

        $listener = new AuthRateLimitListener($factory);

        $request = Request::create($path);
        $event = $this->makeEvent($request);

        ($listener)($event);

        self::assertFalse($event->hasResponse());
        self::assertNull($request->attributes->get('rate_limit_limit'));
    }

    // -----------------------------------------------------------------------
    // Rate limit exceeded
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturns429WhenRateLimitExceeded(): void
    {
        $factory = $this->createLimiterFactory($this->createRejectedRateLimit());
        $listener = new AuthRateLimitListener($factory);

        $request = Request::create('/api/login_check');
        $event = $this->makeEvent($request);

        ($listener)($event);

        self::assertTrue($event->hasResponse());
        self::assertInstanceOf(JsonResponse::class, $event->getResponse());
        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $event->getResponse()->getStatusCode());
    }

    #[Test]
    public function itIncludesRetryAfterHeaderOn429(): void
    {
        $factory = $this->createLimiterFactory($this->createRejectedRateLimit());
        $listener = new AuthRateLimitListener($factory);

        $request = Request::create('/api/login_check');
        $event = $this->makeEvent($request);

        ($listener)($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertTrue($response->headers->has('Retry-After'));
        self::assertTrue($response->headers->has('X-RateLimit-Limit'));
        self::assertSame('0', $response->headers->get('X-RateLimit-Remaining'));
    }

    #[Test]
    public function itIncludesRfc6585ErrorBodyOn429(): void
    {
        $factory = $this->createLimiterFactory($this->createRejectedRateLimit());
        $listener = new AuthRateLimitListener($factory);

        $request = Request::create('/api/v1/auth/register');
        $event = $this->makeEvent($request);

        ($listener)($event);

        $body = json_decode((string) $event->getResponse()->getContent(), true);
        self::assertSame(429, $body['status']);
        self::assertSame('Too Many Requests', $body['title']);
        self::assertStringContainsString('authentication attempts', $body['detail']);
        self::assertArrayHasKey('retry_after', $body);
    }

    // -----------------------------------------------------------------------
    // Sub-request handling
    // -----------------------------------------------------------------------

    #[Test]
    public function itIgnoresSubRequests(): void
    {
        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->expects(self::never())->method('create');

        $listener = new AuthRateLimitListener($factory);

        $request = Request::create('/api/login_check');
        $event = $this->makeEvent($request, isMain: false);

        ($listener)($event);

        self::assertFalse($event->hasResponse());
    }

    // -----------------------------------------------------------------------
    // Rate limit key isolation
    // -----------------------------------------------------------------------

    #[Test]
    public function itUsesClientIpAsLimiterKey(): void
    {
        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->method('consume')->willReturn($this->createAcceptedRateLimit());

        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->expects(self::once())
            ->method('create')
            ->with('auth:10.0.0.1')
            ->willReturn($limiter);

        $listener = new AuthRateLimitListener($factory);

        $request = Request::create('/api/login_check', server: ['REMOTE_ADDR' => '10.0.0.1']);
        $event = $this->makeEvent($request);

        ($listener)($event);
    }

    // -----------------------------------------------------------------------
    // Non-API routes
    // -----------------------------------------------------------------------

    #[Test]
    public function itDoesNothingForNonApiRoutes(): void
    {
        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->expects(self::never())->method('create');

        $listener = new AuthRateLimitListener($factory);

        $request = Request::create('/login');
        $event = $this->makeEvent($request);

        ($listener)($event);

        self::assertFalse($event->hasResponse());
    }
}
