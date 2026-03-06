<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use App\Catalog\Application\Service\SkuGeneratorService;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Infrastructure\ApiPlatform\State\CreateProductProcessor;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(CreateProductProcessor::class)]
final class CreateProductProcessorTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private SkuGeneratorService $skuGenerator;
    private CreateProductProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->skuGenerator = $this->createMock(SkuGeneratorService::class);

        $this->processor = new CreateProductProcessor(
            $this->entityManager,
            $this->requestStack,
            $this->skuGenerator,
        );
    }

    // -----------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------

    #[Test]
    public function itPersistsAndReturnsProductEntity(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $entity = $this->buildProductEntity($tenantId);
        $request = $this->buildRequest($tenantId);

        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        $this->skuGenerator->method('generate')->willReturn(SKU::fromString('PRD-000001'));
        $this->entityManager->expects(self::once())->method('persist')->with($entity);
        $this->entityManager->expects(self::once())->method('flush');

        $operation = $this->createMock(Operation::class);
        $result = $this->processor->process($entity, $operation);

        self::assertSame($entity, $result);
    }

    #[Test]
    public function itSetsTenantIdOnProduct(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $entity = $this->buildProductEntity($tenantId);
        $request = $this->buildRequest($tenantId);

        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        $this->skuGenerator->method('generate')->willReturn(SKU::fromString('PRD-000001'));
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($entity, $operation);

        $ref = new \ReflectionClass($entity);
        $prop = $ref->getProperty('tenantId');
        $prop->setAccessible(true);
        self::assertSame($tenantId, $prop->getValue($entity));
    }

    #[Test]
    public function itGeneratesUuidForProduct(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $entity = $this->buildProductEntity($tenantId);
        $request = $this->buildRequest($tenantId);

        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        $this->skuGenerator->method('generate')->willReturn(SKU::fromString('PRD-000001'));
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
    public function itDelegatesToSkuGeneratorWithTenantId(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $entity = $this->buildProductEntity($tenantId);
        $request = $this->buildRequest($tenantId);

        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $this->skuGenerator->expects(self::once())
            ->method('generate')
            ->with(
                self::callback(fn (TenantId $t) => $t->toString() === $tenantId),
                null,
            )
            ->willReturn(SKU::fromString('PRD-000001'));

        $operation = $this->createMock(Operation::class);
        $this->processor->process($entity, $operation);
    }

    #[Test]
    public function itSetsSkuFromGenerator(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $entity = $this->buildProductEntity($tenantId);
        $request = $this->buildRequest($tenantId);

        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        $this->skuGenerator->method('generate')->willReturn(SKU::fromString('TST-000001'));
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($entity, $operation);

        self::assertSame('TST-000001', $entity->getSku());
    }

    // -----------------------------------------------------------
    // Error paths
    // -----------------------------------------------------------

    #[Test]
    public function itThrowsWhenDataIsNotProductEntity(): void
    {
        $request = $this->buildRequest('00000000-0000-4000-8000-000000000001');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createMock(Operation::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected ProductEntity');
        $this->processor->process(new \stdClass(), $operation);
    }

    #[Test]
    public function itThrowsWhenTenantIdHeaderMissing(): void
    {
        $entity = $this->buildProductEntity('00000000-0000-4000-8000-000000000001');
        $this->requestStack->method('getCurrentRequest')->willReturn(new Request());

        $operation = $this->createMock(Operation::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('X-Tenant-ID header is required');
        $this->processor->process($entity, $operation);
    }

    // -----------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------

    private function buildProductEntity(string $tenantId): ProductEntity
    {
        $entity = new ProductEntity();

        // Set the required fields that would normally come from a POST body
        $ref = new \ReflectionClass($entity);

        $nameP = $ref->getProperty('name');
        $nameP->setAccessible(true);
        $nameP->setValue($entity, 'Test Product');

        $skuP = $ref->getProperty('sku');
        $skuP->setAccessible(true);
        $skuP->setValue($entity, 'PRD-000000');

        return $entity;
    }

    private function buildRequest(string $tenantId): Request
    {
        $request = new Request();
        $request->headers->set('X-Tenant-ID', $tenantId);

        return $request;
    }
}
