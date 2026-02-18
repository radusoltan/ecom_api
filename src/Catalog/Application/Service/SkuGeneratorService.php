<?php

declare(strict_types=1);

namespace App\Catalog\Application\Service;

use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use Doctrine\DBAL\Connection;

final class SkuGeneratorService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {
    }

    public function generate(TenantId $tenantId, ?CategoryId $categoryId = null): SKU
    {
        $prefix = $this->resolvePrefix($tenantId, $categoryId);
        $sequence = $this->getNextSequence($tenantId);

        $sku = sprintf('%s-%06d', $prefix, $sequence);

        return SKU::fromString($sku);
    }

    private function resolvePrefix(TenantId $tenantId, ?CategoryId $categoryId): string
    {
        if (null === $categoryId) {
            return 'PRD';
        }

        $category = $this->categoryRepository->findById($categoryId);

        if (null === $category || !$category->tenantId()->equals($tenantId)) {
            return 'PRD';
        }

        return $this->buildCode($category->name()->value(), 'PRD');
    }

    private function buildCode(string $value, string $default): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        if (false === $transliterated) {
            $transliterated = $value;
        }

        $upper = strtoupper($transliterated);
        $filtered = preg_replace('/[^A-Z0-9]/', '', $upper);

        if (null === $filtered || '' === $filtered) {
            return $default;
        }

        $code = substr($filtered, 0, 3);

        return str_pad($code, 3, 'X');
    }

    private function getNextSequence(TenantId $tenantId): int
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $result = $this->connection->fetchOne(
            <<<'SQL'
INSERT INTO catalog_sku_sequences (tenant_id, last_value, created_at, updated_at)
VALUES (:tenantId, 1, :now, :now)
ON CONFLICT (tenant_id)
DO UPDATE SET
    last_value = catalog_sku_sequences.last_value + 1,
    updated_at = EXCLUDED.updated_at
RETURNING last_value
SQL,
            [
                'tenantId' => $tenantId->toString(),
                'now' => $now,
            ]
        );

        if (false === $result) {
            throw new \RuntimeException('Failed to generate SKU sequence.');
        }

        return (int) $result;
    }
}
