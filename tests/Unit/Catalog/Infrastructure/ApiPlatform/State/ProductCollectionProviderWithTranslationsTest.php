<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\GetCollection;
use App\Catalog\Domain\Service\LocalizationPolicy;
use App\Catalog\Infrastructure\ApiPlatform\State\ProductCollectionProviderWithTranslations;
use App\Catalog\Infrastructure\Cache\I18nCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ProductCollectionProviderWithTranslationsTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private LocalizationPolicy $localizationPolicy;
    private I18nCacheService $cacheService;
    private ProductCollectionProviderWithTranslations $provider;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->localizationPolicy = new LocalizationPolicy();
        $this->cacheService = $this->createMock(I18nCacheService::class);

        $this->provider = new ProductCollectionProviderWithTranslations(
            $this->entityManager,
            $this->requestStack,
            $this->localizationPolicy,
            $this->cacheService,
        );
    }

    public function testThrowsExceptionWhenNoTenantIdHeader(): void
    {
        $request = $this->createMock(Request::class);
        $request->headers = new HeaderBag();
        $request->query = new InputBag();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('X-Tenant-ID header is required');

        $this->provider->provide(new GetCollection());
    }

    public function testProvidesProductsWithTenantId(): void
    {
        $request = $this->createMock(Request::class);
        $request->headers = new HeaderBag([
            'X-Tenant-ID' => '00000000-0000-4000-8000-000000000001',
        ]);
        $request->query = new InputBag();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $queryMock = $this->createMock(Query::class);
        $queryMock->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('setFirstResult')->willReturnSelf();
        $qb->method('getQuery')->willReturn($queryMock);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $result = $this->provider->provide(new GetCollection());

        self::assertIsArray($result);
        self::assertSame([], $result);
    }

    public function testAppliesActiveFilter(): void
    {
        $request = $this->createMock(Request::class);
        $request->headers = new HeaderBag([
            'X-Tenant-ID' => '00000000-0000-4000-8000-000000000001',
        ]);
        $request->query = new InputBag(['active' => 'true', 'sort' => 'name', 'order' => 'ASC']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $queryMock = $this->createMock(Query::class);
        $queryMock->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->expects(self::atLeastOnce())->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('setFirstResult')->willReturnSelf();
        $qb->method('getQuery')->willReturn($queryMock);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $result = $this->provider->provide(new GetCollection());

        self::assertSame([], $result);
    }

    public function testResolvesLocaleFromQueryParameter(): void
    {
        $request = $this->createMock(Request::class);
        $request->headers = new HeaderBag([
            'X-Tenant-ID' => '00000000-0000-4000-8000-000000000001',
        ]);
        $request->query = new InputBag(['locale' => 'fr-FR']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $queryMock = $this->createMock(Query::class);
        $queryMock->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('setFirstResult')->willReturnSelf();
        $qb->method('getQuery')->willReturn($queryMock);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        // Should not throw - locale 'fr_FR' is valid
        $result = $this->provider->provide(new GetCollection());

        self::assertSame([], $result);
    }

    public function testAppliesSearchFilter(): void
    {
        $request = $this->createMock(Request::class);
        $request->headers = new HeaderBag([
            'X-Tenant-ID' => '00000000-0000-4000-8000-000000000001',
        ]);
        $request->query = new InputBag(['search' => 'laptop']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $queryMock = $this->createMock(Query::class);
        $queryMock->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->expects(self::atLeastOnce())->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('setFirstResult')->willReturnSelf();
        $qb->method('getQuery')->willReturn($queryMock);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $result = $this->provider->provide(new GetCollection());

        self::assertSame([], $result);
    }

    public function testAppliesPagination(): void
    {
        $request = $this->createMock(Request::class);
        $request->headers = new HeaderBag([
            'X-Tenant-ID' => '00000000-0000-4000-8000-000000000001',
        ]);
        $request->query = new InputBag(['page' => '2', 'limit' => '10']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $queryMock = $this->createMock(Query::class);
        $queryMock->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->expects(self::once())->method('setMaxResults')->with(10)->willReturnSelf();
        $qb->expects(self::once())->method('setFirstResult')->with(10)->willReturnSelf();
        $qb->method('getQuery')->willReturn($queryMock);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $result = $this->provider->provide(new GetCollection());

        self::assertSame([], $result);
    }
}
