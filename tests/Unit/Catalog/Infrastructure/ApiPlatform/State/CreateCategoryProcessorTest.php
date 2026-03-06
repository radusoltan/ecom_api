<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use App\Catalog\Infrastructure\ApiPlatform\State\CreateCategoryProcessor;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\CategoryEntity;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(CreateCategoryProcessor::class)]
final class CreateCategoryProcessorTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private CreateCategoryProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->processor = new CreateCategoryProcessor(
            $this->entityManager,
            $this->requestStack,
        );
    }

    // -----------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------

    #[Test]
    public function itPersistsAndReturnsCategoryEntityWithGeneratedId(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $entity = new CategoryEntity();

        $request = $this->buildRequest($tenantId);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->entityManager->expects(self::once())->method('persist')->with($entity);
        $this->entityManager->expects(self::once())->method('flush');

        $operation = $this->createMock(Operation::class);
        $result = $this->processor->process($entity, $operation);

        self::assertSame($entity, $result);
    }

    #[Test]
    public function itSetsTenantIdOnEntity(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $entity = new CategoryEntity();

        $request = $this->buildRequest($tenantId);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($entity, $operation);

        // TenantId is set via setTenantId() — verify via reflection
        $ref = new \ReflectionClass($entity);
        $prop = $ref->getProperty('tenantId');
        $prop->setAccessible(true);
        self::assertSame($tenantId, $prop->getValue($entity));
    }

    #[Test]
    public function itSetsNonEmptyUuidAsId(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $entity = new CategoryEntity();

        $request = $this->buildRequest($tenantId);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($entity, $operation);

        $ref = new \ReflectionClass($entity);
        $prop = $ref->getProperty('id');
        $prop->setAccessible(true);
        $id = $prop->getValue($entity);

        self::assertNotEmpty($id);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', $id);
    }

    #[Test]
    public function itSetsTimestampsOnEntity(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $entity = new CategoryEntity();

        $request = $this->buildRequest($tenantId);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($entity, $operation);

        $ref = new \ReflectionClass($entity);

        $createdAt = $ref->getProperty('createdAt');
        $createdAt->setAccessible(true);
        self::assertInstanceOf(\DateTimeImmutable::class, $createdAt->getValue($entity));

        $updatedAt = $ref->getProperty('updatedAt');
        $updatedAt->setAccessible(true);
        self::assertInstanceOf(\DateTimeImmutable::class, $updatedAt->getValue($entity));
    }

    // -----------------------------------------------------------
    // Error paths
    // -----------------------------------------------------------

    #[Test]
    public function itThrowsWhenDataIsNotCategoryEntity(): void
    {
        $request = $this->buildRequest('00000000-0000-4000-8000-000000000001');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createMock(Operation::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->processor->process(new \stdClass(), $operation);
    }

    #[Test]
    public function itThrowsWhenTenantIdHeaderIsMissing(): void
    {
        $request = new Request();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createMock(Operation::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('X-Tenant-ID header is required');
        $this->processor->process(new CategoryEntity(), $operation);
    }

    #[Test]
    public function itThrowsWhenNoActiveRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $operation = $this->createMock(Operation::class);

        $this->expectException(\RuntimeException::class);
        $this->processor->process(new CategoryEntity(), $operation);
    }

    // -----------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------

    private function buildRequest(string $tenantId): Request
    {
        $request = new Request();
        $request->headers->set('X-Tenant-ID', $tenantId);

        return $request;
    }
}
