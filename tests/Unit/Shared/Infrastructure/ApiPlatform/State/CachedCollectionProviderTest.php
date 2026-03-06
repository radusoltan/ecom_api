<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Infrastructure\ApiPlatform\State\CachedCollectionProvider;
use App\Shared\Infrastructure\Cache\CacheService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CachedCollectionProviderTest extends TestCase
{
    private ProviderInterface $decorated;
    private CacheService $cacheService;
    private RequestStack $requestStack;
    private LoggerInterface $logger;
    private CachedCollectionProvider $provider;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(ProviderInterface::class);
        $this->cacheService = $this->createMock(CacheService::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->provider = new CachedCollectionProvider(
            $this->decorated,
            $this->cacheService,
            $this->requestStack,
            $this->logger,
        );
    }

    private function createRequest(string $method = 'GET', array $headers = [], ?string $queryString = null): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn($method);
        $request->headers = new HeaderBag($headers);
        $request->method('getPreferredLanguage')->willReturn('en');
        $request->method('getQueryString')->willReturn($queryString);

        return $request;
    }

    private function createOperation(string $shortName = 'ProductList'): Operation
    {
        $operation = $this->createMock(Operation::class);
        $operation->method('getShortName')->willReturn($shortName);

        return $operation;
    }

    public function testDelegatesToDecoratedWhenNoRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $operation = $this->createOperation();
        $expected = ['data'];

        $this->decorated->expects(self::once())->method('provide')->willReturn($expected);

        $result = $this->provider->provide($operation);

        self::assertSame($expected, $result);
    }

    public function testDelegatesToDecoratedForNonGetRequests(): void
    {
        $request = $this->createRequest('POST');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createOperation();
        $expected = ['data'];

        $this->decorated->expects(self::once())->method('provide')->willReturn($expected);

        $result = $this->provider->provide($operation);

        self::assertSame($expected, $result);
    }

    public function testBypassesCacheWhenNoCacheHeaderSet(): void
    {
        $request = $this->createRequest('GET', ['X-No-Cache' => 'true']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createOperation();
        $expected = ['fresh_data'];

        $this->decorated->expects(self::once())->method('provide')->willReturn($expected);
        $this->logger->expects(self::once())->method('debug');

        $result = $this->provider->provide($operation);

        self::assertSame($expected, $result);
    }

    public function testUsesCacheForGetRequests(): void
    {
        $request = $this->createRequest('GET', [
            'X-Tenant-ID' => '00000000-0000-4000-8000-000000000001',
        ]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createOperation('ProductList');
        $cachedData = ['cached_products'];

        $this->cacheService->method('tenantKey')->willReturn('tenant:t1:api:ProductList:en:hash');
        $this->cacheService->method('get')->willReturn($cachedData);

        $result = $this->provider->provide($operation);

        self::assertSame($cachedData, $result);
    }

    public function testFallsBackToDecoratedOnCacheException(): void
    {
        $request = $this->createRequest('GET', [
            'X-Tenant-ID' => '00000000-0000-4000-8000-000000000001',
        ]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createOperation();
        $expected = ['fallback_data'];

        $this->cacheService->method('tenantKey')->willReturn('key');
        $this->cacheService->method('get')->willThrowException(new \RuntimeException('Cache down'));

        $this->decorated->expects(self::once())->method('provide')->willReturn($expected);
        $this->logger->expects(self::once())->method('warning');

        $result = $this->provider->provide($operation);

        self::assertSame($expected, $result);
    }

    public function testTtlForProductOperation(): void
    {
        $request = $this->createRequest('GET', [
            'X-Tenant-ID' => '00000000-0000-4000-8000-000000000001',
        ]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createOperation('ProductList');

        $this->cacheService->method('tenantKey')->willReturn('key');
        $this->cacheService->expects(self::once())->method('get')->with(
            self::anything(),
            self::anything(),
            300, // 5 minutes for Product
            self::anything(),
        )->willReturn([]);

        $this->provider->provide($operation);
    }

    public function testTtlForCategoryOperation(): void
    {
        $request = $this->createRequest('GET', [
            'X-Tenant-ID' => '00000000-0000-4000-8000-000000000001',
        ]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createOperation('CategoryList');

        $this->cacheService->method('tenantKey')->willReturn('key');
        $this->cacheService->expects(self::once())->method('get')->with(
            self::anything(),
            self::anything(),
            600, // 10 minutes for Category
            self::anything(),
        )->willReturn([]);

        $this->provider->provide($operation);
    }

    public function testTtlZeroForOrderOperation(): void
    {
        $request = $this->createRequest('GET', [
            'X-Tenant-ID' => '00000000-0000-4000-8000-000000000001',
        ]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createOperation('OrderList');

        $this->cacheService->method('tenantKey')->willReturn('key');
        $this->cacheService->expects(self::once())->method('get')->with(
            self::anything(),
            self::anything(),
            0, // No caching for Order
            self::anything(),
        )->willReturn([]);

        $this->provider->provide($operation);
    }

    public function testGeneratesProductTags(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $request = $this->createRequest('GET', ['X-Tenant-ID' => $tenantId]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createOperation('ProductList');

        $this->cacheService->method('tenantKey')->willReturn('key');
        $this->cacheService->expects(self::once())->method('get')->with(
            self::anything(),
            self::anything(),
            self::anything(),
            self::callback(function (array $tags) use ($tenantId) {
                return in_array('api', $tags, true)
                    && in_array('tenant:'.$tenantId, $tags, true)
                    && in_array('products', $tags, true)
                    && in_array('tenant:'.$tenantId.':products', $tags, true);
            }),
        )->willReturn([]);

        $this->provider->provide($operation);
    }

    public function testDefaultTenantIdWhenHeaderMissing(): void
    {
        $request = $this->createRequest('GET');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createOperation('SomeResource');

        $this->cacheService->expects(self::once())->method('tenantKey')
            ->with('default', self::anything())
            ->willReturn('key');
        $this->cacheService->method('get')->willReturn([]);

        $this->provider->provide($operation);
    }
}
