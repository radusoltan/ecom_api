<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Search\Application\Query\AutocompleteProducts;
use App\Search\Application\Query\SearchProducts;
use App\Search\Domain\Model\ProductSearchHit;
use App\Search\Domain\Model\SearchResult;
use App\Search\Infrastructure\ApiPlatform\State\SearchProvider;
use App\Search\Presentation\Api\Resource\SearchResultResource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class SearchProviderTest extends TestCase
{
    private MessageBusInterface $queryBus;
    private RequestStack $requestStack;
    private SearchProvider $provider;

    protected function setUp(): void
    {
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        $this->provider = new SearchProvider(
            $this->queryBus,
            $this->requestStack,
        );
    }

    private function createRequest(array $query = [], ?string $acceptLanguage = 'en'): Request
    {
        $request = $this->createMock(Request::class);
        $request->query = new InputBag($query);
        $headerData = [];
        if (null !== $acceptLanguage) {
            $headerData['Accept-Language'] = $acceptLanguage;
        }
        $request->headers = new HeaderBag($headerData);

        return $request;
    }

    public function testThrowsRuntimeExceptionWhenNoRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No request available');

        $this->provider->provide(new GetCollection(uriTemplate: '/search'), [], ['tenant_id' => 'tid']);
    }

    public function testThrowsBadRequestWhenTenantIdMissing(): void
    {
        $request = $this->createRequest();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->expectException(BadRequestHttpException::class);

        $this->provider->provide(new GetCollection(uriTemplate: '/search'));
    }

    public function testHandleSearchDispatchesQueryAndReturnsResource(): void
    {
        $request = $this->createRequest(
            ['q' => 'laptop', 'page' => '2', 'per_page' => '10', 'sort_by' => 'price', 'sort_order' => 'asc'],
            'en'
        );
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $searchResult = new SearchResult(
            hits: [],
            total: 0,
            page: 2,
            perPage: 10,
            facets: [],
            took: 5.0,
        );

        $handledStamp = new HandledStamp($searchResult, 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);

        $this->queryBus->expects(self::once())->method('dispatch')
            ->with(self::callback(function ($query) {
                return $query instanceof SearchProducts
                    && 'laptop' === $query->query
                    && 2 === $query->page
                    && 10 === $query->perPage
                    && 'price' === $query->sortBy
                    && 'asc' === $query->sortOrder;
            }))
            ->willReturn($envelope);

        $result = $this->provider->provide(
            new GetCollection(uriTemplate: '/search'),
            [],
            ['tenant_id' => '00000000-0000-4000-8000-000000000001']
        );

        self::assertInstanceOf(SearchResultResource::class, $result);
    }

    public function testHandleSearchWithFilters(): void
    {
        $request = $this->createRequest([
            'q' => 'phone',
            'filter' => [
                'category' => 'electronics',
                'price_min' => '100',
                'price_max' => '500',
                'in_stock_only' => '1',
            ],
        ], 'en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $searchResult = new SearchResult(hits: [], total: 0, page: 1, perPage: 24, facets: [], took: 1.0);
        $handledStamp = new HandledStamp($searchResult, 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);

        $this->queryBus->expects(self::once())->method('dispatch')
            ->with(self::callback(function (SearchProducts $q) {
                return 'electronics' === $q->filters['category']
                    && 100 === $q->filters['price_min']
                    && 500 === $q->filters['price_max']
                    && true === $q->filters['in_stock_only'];
            }))
            ->willReturn($envelope);

        $this->provider->provide(
            new GetCollection(uriTemplate: '/search'),
            [],
            ['tenant_id' => 'tid']
        );
    }

    public function testHandleAutocomplete(): void
    {
        $request = $this->createRequest(
            ['q' => 'lap', 'limit' => '3'],
            'en'
        );
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $hit = $this->createMock(ProductSearchHit::class);
        $hit->method('toArray')->willReturn(['sku' => 'LAP-1', 'name' => 'Laptop']);

        $handledStamp = new HandledStamp([$hit], 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);

        $this->queryBus->expects(self::once())->method('dispatch')
            ->with(self::callback(function (AutocompleteProducts $q) {
                return 'lap' === $q->query && 3 === $q->limit;
            }))
            ->willReturn($envelope);

        $result = $this->provider->provide(
            new Get(uriTemplate: '/search/autocomplete'),
            [],
            ['tenant_id' => 'tid']
        );

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertSame('LAP-1', $result[0]['sku']);
    }

    public function testThrowsWhenSearchHandlerReturnsNull(): void
    {
        $request = $this->createRequest(['q' => 'test'], 'en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $envelope = new Envelope(new \stdClass());
        $this->queryBus->method('dispatch')->willReturn($envelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Search query handler did not return a result');

        $this->provider->provide(
            new GetCollection(uriTemplate: '/search'),
            [],
            ['tenant_id' => 'tid']
        );
    }

    public function testThrowsWhenAutocompleteHandlerReturnsNull(): void
    {
        $request = $this->createRequest(['q' => 'test'], 'en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $envelope = new Envelope(new \stdClass());
        $this->queryBus->method('dispatch')->willReturn($envelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Autocomplete query handler did not return a result');

        $this->provider->provide(
            new Get(uriTemplate: '/search/autocomplete'),
            [],
            ['tenant_id' => 'tid']
        );
    }

    public function testLocaleExtractionFromAcceptLanguageWithDash(): void
    {
        $request = $this->createRequest(['q' => 'test'], 'en-US');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $searchResult = new SearchResult(hits: [], total: 0, page: 1, perPage: 24, facets: [], took: 0.0);
        $handledStamp = new HandledStamp($searchResult, 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);

        $this->queryBus->expects(self::once())->method('dispatch')
            ->with(self::callback(fn (SearchProducts $q) => 'en' === $q->locale))
            ->willReturn($envelope);

        $this->provider->provide(
            new GetCollection(uriTemplate: '/search'),
            [],
            ['tenant_id' => 'tid']
        );
    }

    public function testLocaleExtractionFromAcceptLanguageWithUnderscore(): void
    {
        $request = $this->createRequest(['q' => 'test'], 'fr_FR');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $searchResult = new SearchResult(hits: [], total: 0, page: 1, perPage: 24, facets: [], took: 0.0);
        $handledStamp = new HandledStamp($searchResult, 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);

        $this->queryBus->expects(self::once())->method('dispatch')
            ->with(self::callback(fn (SearchProducts $q) => 'fr' === $q->locale))
            ->willReturn($envelope);

        $this->provider->provide(
            new GetCollection(uriTemplate: '/search'),
            [],
            ['tenant_id' => 'tid']
        );
    }

    public function testDefaultLocaleWhenNoAcceptLanguage(): void
    {
        $request = $this->createRequest(['q' => 'test'], null);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $searchResult = new SearchResult(hits: [], total: 0, page: 1, perPage: 24, facets: [], took: 0.0);
        $handledStamp = new HandledStamp($searchResult, 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);

        $this->queryBus->expects(self::once())->method('dispatch')
            ->with(self::callback(fn (SearchProducts $q) => 'en' === $q->locale))
            ->willReturn($envelope);

        $this->provider->provide(
            new GetCollection(uriTemplate: '/search'),
            [],
            ['tenant_id' => 'tid']
        );
    }
}
