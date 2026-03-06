<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Messenger;

use App\Shared\Infrastructure\Messenger\TenantContextMiddleware;
use App\Shared\Infrastructure\Messenger\TenantStamp;
use App\Shared\Infrastructure\Tenant\TenantContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

#[CoversClass(TenantContextMiddleware::class)]
final class TenantContextMiddlewareTest extends TestCase
{
    private TenantContext&MockObject $tenantContext;
    private LoggerInterface&MockObject $logger;
    private TenantContextMiddleware $middleware;

    protected function setUp(): void
    {
        $this->tenantContext = $this->createMock(TenantContext::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->middleware = new TenantContextMiddleware($this->tenantContext, $this->logger);
    }

    // -----------------------------------------------------------------------
    // PRODUCER path (no ReceivedStamp)
    // -----------------------------------------------------------------------

    #[Test]
    public function itAddsTenantStampOnDispatchWhenTenantIsSet(): void
    {
        $tenantId = $this->createMock(\App\Shared\Domain\ValueObject\TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        $this->tenantContext->method('hasCurrentTenant')->willReturn(true);
        $this->tenantContext->method('getCurrentTenantId')->willReturn($tenantId);

        $envelope = new Envelope(new \stdClass());
        $stack = $this->createStackThatCaptures($returnedEnvelope);

        $this->middleware->handle($envelope, $stack);

        self::assertNotNull($returnedEnvelope->last(TenantStamp::class));
        self::assertSame(
            '00000000-0000-4000-8000-000000000001',
            $returnedEnvelope->last(TenantStamp::class)->getTenantId()
        );
    }

    #[Test]
    public function itDoesNotAddTenantStampWhenNoCurrentTenant(): void
    {
        $this->tenantContext->method('hasCurrentTenant')->willReturn(false);

        $envelope = new Envelope(new \stdClass());
        $stack = $this->createStackThatCaptures($returnedEnvelope);

        $this->middleware->handle($envelope, $stack);

        self::assertNull($returnedEnvelope->last(TenantStamp::class));
    }

    #[Test]
    public function itDoesNotDuplicateStampIfAlreadyPresent(): void
    {
        $tenantId = $this->createMock(\App\Shared\Domain\ValueObject\TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        $this->tenantContext->method('hasCurrentTenant')->willReturn(true);
        $this->tenantContext->method('getCurrentTenantId')->willReturn($tenantId);

        $existingStamp = new TenantStamp('00000000-0000-4000-8000-000000000001');
        $envelope = new Envelope(new \stdClass(), [$existingStamp]);
        $stack = $this->createStackThatCaptures($returnedEnvelope);

        $this->middleware->handle($envelope, $stack);

        // Should still only have one stamp (the pre-existing one)
        $stamps = $returnedEnvelope->all(TenantStamp::class);
        self::assertCount(1, $stamps);
    }

    // -----------------------------------------------------------------------
    // CONSUMER path (has ReceivedStamp)
    // -----------------------------------------------------------------------

    #[Test]
    public function itRestoresTenantContextOnConsume(): void
    {
        $this->tenantContext
            ->expects(self::once())
            ->method('setCurrentTenant')
            ->with(self::callback(
                fn ($tenantId) => '00000000-0000-4000-8000-000000000001' === $tenantId->toString()
            ));

        $this->tenantContext->expects(self::once())->method('clearCurrentTenant');

        $envelope = new Envelope(new \stdClass(), [
            new ReceivedStamp('transport'),
            new TenantStamp('00000000-0000-4000-8000-000000000001'),
        ]);

        $stack = $this->createPassthroughStack();

        $this->middleware->handle($envelope, $stack);
    }

    #[Test]
    public function itClearsTenantContextAfterConsumption(): void
    {
        $cleared = false;
        $this->tenantContext->method('setCurrentTenant');
        $this->tenantContext
            ->expects(self::once())
            ->method('clearCurrentTenant')
            ->willReturnCallback(function () use (&$cleared) {
                $cleared = true;
            });

        $envelope = new Envelope(new \stdClass(), [
            new ReceivedStamp('transport'),
            new TenantStamp('00000000-0000-4000-8000-000000000001'),
        ]);

        $this->middleware->handle($envelope, $this->createPassthroughStack());

        self::assertTrue($cleared);
    }

    #[Test]
    public function itLogsWarningWhenConsumedWithoutStamp(): void
    {
        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('without TenantStamp'));

        $this->tenantContext->expects(self::never())->method('setCurrentTenant');

        $envelope = new Envelope(new \stdClass(), [
            new ReceivedStamp('transport'),
            // No TenantStamp
        ]);

        $this->middleware->handle($envelope, $this->createPassthroughStack());
    }

    // -----------------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------------

    /**
     * Creates a StackInterface that passes the envelope on and captures it.
     */
    private function createStackThatCaptures(?Envelope &$captured): StackInterface
    {
        $next = $this->createMock(MiddlewareInterface::class);
        $next->method('handle')->willReturnCallback(function (Envelope $e) use (&$captured) {
            $captured = $e;

            return $e;
        });

        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($next);

        return $stack;
    }

    private function createPassthroughStack(): StackInterface
    {
        $next = $this->createMock(MiddlewareInterface::class);
        $next->method('handle')->willReturnCallback(fn (Envelope $e) => $e);

        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($next);

        return $stack;
    }
}
