<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Infrastructure\ApiPlatform\State\TenantContextProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(TenantContextProvider::class)]
final class TenantContextProviderTest extends TestCase
{
    private ProviderInterface&MockObject $decorated;
    private RequestStack&MockObject $requestStack;
    private TenantContextProvider $provider;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(ProviderInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->provider = new TenantContextProvider($this->decorated, $this->requestStack);
    }

    #[Test]
    public function itInjectsTenantIdIntoContextWhenHeaderPresent(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $request = $this->createRequestWithTenantHeader($tenantId);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = new Get();

        $this->decorated
            ->expects(self::once())
            ->method('provide')
            ->with(
                $operation,
                [],
                self::callback(fn (array $ctx) => $ctx['tenant_id'] === $tenantId),
            )
            ->willReturn([]);

        $this->provider->provide($operation, [], []);
    }

    #[Test]
    public function itDoesNotInjectTenantIdWhenHeaderAbsent(): void
    {
        $request = new Request();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = new Get();

        $this->decorated
            ->expects(self::once())
            ->method('provide')
            ->with(
                $operation,
                [],
                self::callback(fn (array $ctx) => !array_key_exists('tenant_id', $ctx)),
            )
            ->willReturn([]);

        $this->provider->provide($operation, [], []);
    }

    #[Test]
    public function itDoesNotInjectTenantIdWhenNoCurrentRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $operation = new Get();

        $this->decorated
            ->expects(self::once())
            ->method('provide')
            ->with(
                $operation,
                [],
                self::callback(fn (array $ctx) => !array_key_exists('tenant_id', $ctx)),
            )
            ->willReturn(null);

        $result = $this->provider->provide($operation, [], []);

        self::assertNull($result);
    }

    #[Test]
    public function itForwardsUriVariables(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $operation = new Get();
        $uriVariables = ['id' => 'abc-123'];

        $this->decorated
            ->expects(self::once())
            ->method('provide')
            ->with($operation, $uriVariables, self::anything())
            ->willReturn(new \stdClass());

        $this->provider->provide($operation, $uriVariables, []);
    }

    #[Test]
    public function itReturnsDecoratedResult(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $expected = ['data' => 'value'];
        $this->decorated->method('provide')->willReturn($expected);

        $result = $this->provider->provide(new Get(), [], []);

        self::assertSame($expected, $result);
    }

    #[Test]
    public function itMergesWithExistingContext(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $request = $this->createRequestWithTenantHeader($tenantId);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = new Get();
        $existingContext = ['groups' => ['read']];

        $this->decorated
            ->expects(self::once())
            ->method('provide')
            ->with(
                $operation,
                [],
                self::callback(
                    fn (array $ctx) => isset($ctx['tenant_id']) && isset($ctx['groups'])
                ),
            )
            ->willReturn([]);

        $this->provider->provide($operation, [], $existingContext);
    }

    // -----------------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------------

    private function createRequestWithTenantHeader(string $tenantId): Request
    {
        $request = new Request();
        $request->headers->set('X-Tenant-ID', $tenantId);

        return $request;
    }
}
