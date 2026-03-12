<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Infrastructure\ApiPlatform\State\CachedCollectionProvider;
use App\Shared\Infrastructure\Cache\CacheService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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

    public function testDelegatesToDecoratedWhenNoRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $operation = $this->createCollectionOperation();
        $expected = ['data'];

        $this->decorated->expects(self::once())->method('provide')->willReturn($expected);

        $result = $this->provider->provide($operation);

        self::assertSame($expected, $result);
    }

    public function testDelegatesToDecoratedForNonGetRequests(): void
    {
        $request = $this->createRequest('POST');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createCollectionOperation();
        $expected = ['data'];

        $this->decorated->expects(self::once())->method('provide')->willReturn($expected);

        $result = $this->provider->provide($operation);

        self::assertSame($expected, $result);
    }

    public function testDelegatesToDecoratedForItemOperations(): void
    {
        $request = $this->createRequest();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = new Get(uriTemplate: '/products/{id}', shortName: 'Product', name: 'get_product');
        $expected = ['item'];

        $this->decorated->expects(self::once())->method('provide')->willReturn($expected);
        $this->cacheService->expects(self::never())->method('get');

        $result = $this->provider->provide($operation);

        self::assertSame($expected, $result);
    }

    public function testBypassesCacheWhenNoCacheHeaderSet(): void
    {
        $request = $this->createRequest(headers: ['X-No-Cache' => 'true']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createCollectionOperation();
        $expected = ['fresh_data'];

        $this->decorated->expects(self::once())->method('provide')->willReturn($expected);
        $this->cacheService->expects(self::never())->method('get');
        $this->logger->expects(self::once())->method('debug');

        $result = $this->provider->provide($operation);

        self::assertSame($expected, $result);
    }

    public function testUsesCacheForGetCollections(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $request = $this->createRequest(
            headers: [
                'X-Tenant-ID' => $tenantId,
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
            query: ['page' => '1'],
            path: '/api/v1/products',
        );
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createCollectionOperation(shortName: 'Product', name: 'get_products', uriTemplate: '/products');
        $cachedData = ['cached_products'];

        $this->cacheService->expects(self::once())
            ->method('tenantQueryKey')
            ->with(
                $tenantId,
                'api',
                'products',
                self::callback(static function (array $query): bool {
                    return 'get_products' === $query['operation']
                        && 'en' === $query['locale']
                        && '/api/v1/products' === $query['path']
                        && $query['query'] === ['page' => '1'];
                })
            )
            ->willReturn('tenant-cache-key');
        $this->cacheService->expects(self::once())->method('tag')->with('api')->willReturn('api');
        $this->cacheService->expects(self::once())
            ->method('tenantScopedTags')
            ->with($tenantId, 'products')
            ->willReturn(['tenant-tag', 'products-tag', 'tenant-products-tag']);
        $this->cacheService->expects(self::once())
            ->method('get')
            ->with(
                'tenant-cache-key',
                self::isCallable(),
                300,
                ['api', 'tenant-tag', 'products-tag', 'tenant-products-tag']
            )
            ->willReturn($cachedData);

        $result = $this->provider->provide($operation);

        self::assertSame($cachedData, $result);
    }

    public function testFallsBackToDecoratedOnCacheException(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $request = $this->createRequest(headers: ['X-Tenant-ID' => $tenantId]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createCollectionOperation(shortName: 'Product', name: 'get_products');
        $expected = ['fallback_data'];

        $this->cacheService->method('tenantQueryKey')->willReturn('cache-key');
        $this->cacheService->method('tag')->willReturn('api');
        $this->cacheService->method('tenantScopedTags')->willReturn(['tenant-products-tag']);
        $this->cacheService->method('get')->willThrowException(new \RuntimeException('Cache down'));

        $this->decorated->expects(self::once())->method('provide')->willReturn($expected);
        $this->logger->expects(self::once())->method('warning');

        $result = $this->provider->provide($operation);

        self::assertSame($expected, $result);
    }

    public function testSkipsCachingForOrderCollections(): void
    {
        $request = $this->createRequest(path: '/api/v1/orders');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createCollectionOperation(shortName: 'Order', name: 'get_orders', uriTemplate: '/orders');
        $expected = ['orders'];

        $this->decorated->expects(self::once())->method('provide')->willReturn($expected);
        $this->cacheService->expects(self::never())->method('tenantQueryKey');
        $this->cacheService->expects(self::never())->method('get');

        $result = $this->provider->provide($operation);

        self::assertSame($expected, $result);
    }

    public function testIncludesOperationIdentityInCacheKeyPayload(): void
    {
        $request = $this->createRequest(path: '/api/v1/storefront/featured-products');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $queries = [];
        $this->cacheService->method('tenantQueryKey')->willReturnCallback(
            static function (string $tenantId, string $context, string $resource, array $query) use (&$queries): string {
                $queries[] = $query;

                return 'cache-key-'.count($queries);
            }
        );
        $this->cacheService->method('tag')->willReturn('api');
        $this->cacheService->method('tenantScopedTags')->willReturn(['tenant-products-tag']);
        $this->cacheService->method('get')->willReturn([]);

        $this->provider->provide(
            new GetCollection(
                uriTemplate: '/storefront/featured-products',
                shortName: 'StorefrontProduct',
                name: 'get_featured_products'
            )
        );

        $this->provider->provide(
            new GetCollection(
                uriTemplate: '/storefront/products',
                shortName: 'StorefrontProduct',
                name: 'get_storefront_products'
            )
        );

        self::assertCount(2, $queries);
        self::assertSame('get_featured_products', $queries[0]['operation']);
        self::assertSame('get_storefront_products', $queries[1]['operation']);
        self::assertNotSame($queries[0]['operation'], $queries[1]['operation']);
    }

    public function testDefaultsTenantIdWhenHeaderMissing(): void
    {
        $request = $this->createRequest(path: '/api/v1/categories');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createCollectionOperation(shortName: 'Category', name: 'get_categories', uriTemplate: '/categories');

        $this->cacheService->expects(self::once())->method('tenantQueryKey')
            ->with('default', 'api', 'categories', self::anything())
            ->willReturn('cache-key');
        $this->cacheService->expects(self::once())->method('tag')->with('api')->willReturn('api');
        $this->cacheService->expects(self::once())->method('tenantScopedTags')
            ->with('default', 'categories')
            ->willReturn(['tenant-categories-tag']);
        $this->cacheService->expects(self::once())->method('get')
            ->with('cache-key', self::isCallable(), 300, ['api', 'tenant-categories-tag'])
            ->willReturn([]);

        $this->provider->provide($operation);
    }

    private function createRequest(
        string $method = 'GET',
        array $headers = [],
        array $query = [],
        string $path = '/api/v1/products',
    ): Request {
        $request = new Request(
            $query,
            [],
            [],
            [],
            [],
            [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $path,
                'PATH_INFO' => $path,
            ]
        );

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    private function createCollectionOperation(
        string $shortName = 'Product',
        string $name = 'get_products',
        string $uriTemplate = '/products',
    ): Operation {
        return new GetCollection(
            uriTemplate: $uriTemplate,
            shortName: $shortName,
            name: $name,
        );
    }
}
