<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\Service\SkuGeneratorService;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * Custom processor for creating products.
 * Generates ID, tenant ID, and timestamps before persisting.
 */
final readonly class CreateProductProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private SkuGeneratorService $skuGenerator
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductEntity
    {
        if (!$data instanceof ProductEntity) {
            throw new \InvalidArgumentException('Expected ProductEntity');
        }

        // Get tenant ID from request header
        $request = $this->requestStack->getCurrentRequest();
        $tenantId = $request?->headers->get('X-Tenant-ID');

        if (!$tenantId) {
            throw new \RuntimeException('X-Tenant-ID header is required');
        }

        // Generate UUID for the product
        $productId = Uuid::v4()->toString();

        // Use reflection to set the ID (since there's no setter)
        $reflection = new \ReflectionClass($data);

        // Set ID
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($data, $productId);

        // Set tenant ID
        $data->setTenantId($tenantId);

        $tenantIdVo = TenantId::fromString($tenantId);
        $categoryIdValue = $data->getCategoryId();
        $categoryIdVo = null;

        if ($categoryIdValue !== null) {
            try {
                $categoryIdVo = CategoryId::fromString($categoryIdValue);
            } catch (\InvalidArgumentException) {
                $categoryIdVo = null;
            }
        }

        $generatedSku = $this->skuGenerator->generate($tenantIdVo, $categoryIdVo);
        $data->setSku($generatedSku->value());

        // Set timestamps
        $now = new \DateTimeImmutable();
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue($data, $now);

        $updatedAtProperty = $reflection->getProperty('updatedAt');
        $updatedAtProperty->setAccessible(true);
        $updatedAtProperty->setValue($data, $now);

        // Persist the entity
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
