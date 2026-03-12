<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Persistence\Doctrine\ReadModel;

use App\Order\Application\DTO\OrderListDto;
use App\Shared\Infrastructure\Encryption\EncryptionService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class OrderListingReadRepository
{
    public function __construct(
        private Connection $connection,
        private EncryptionService $encryptionService,
    ) {
    }

    /**
     * @return array{orders: list<OrderListDto>, total: int}
     */
    public function findForAdmin(
        string $tenantId,
        int $page = 1,
        int $itemsPerPage = 1000,
        ?string $status = null,
        string $sort = 'newest',
    ): array {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $where = ['o.tenant_id = :tenantId'];
        $params = ['tenantId' => $tenantId];
        $types = ['tenantId' => ParameterType::STRING];

        if (null !== $status && '' !== $status) {
            $where[] = 'o.status = :status';
            $params['status'] = $status;
            $types['status'] = ParameterType::STRING;
        }

        $whereClause = implode(' AND ', $where);
        $orderBy = match ($sort) {
            'oldest' => 'o.created_at ASC',
            'status' => 'o.status ASC, o.created_at DESC',
            'total_asc' => 'total_amount ASC, o.created_at DESC',
            'total_desc' => 'total_amount DESC, o.created_at DESC',
            default => 'o.created_at DESC',
        };

        $total = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM orders o WHERE {$whereClause}",
            $params,
            $types,
        );

        $offset = ($page - 1) * $itemsPerPage;
        $sql = <<<SQL
            SELECT
                o.id,
                o.tenant_id,
                o.status,
                o.customer_email,
                jsonb_array_length(o.lines::jsonb) AS line_count,
                COALESCE((
                    SELECT SUM(((item->>'unitPriceAmount')::int) * ((item->>'quantity')::int))
                    FROM jsonb_array_elements(o.lines::jsonb) AS item
                ), 0) AS total_amount,
                COALESCE(
                    o.lines::jsonb->0->>'unitPriceCurrency',
                    o.discount_currency,
                    o.tax_currency,
                    'USD'
                ) AS total_currency,
                o.discount_amount,
                o.coupon_code,
                o.created_at,
                o.updated_at
            FROM orders o
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset
        SQL;

        $params['limit'] = $itemsPerPage;
        $params['offset'] = $offset;
        $types['limit'] = ParameterType::INTEGER;
        $types['offset'] = ParameterType::INTEGER;

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);

        return [
            'orders' => array_map($this->rowToDto(...), $rows),
            'total' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToDto(array $row): OrderListDto
    {
        return new OrderListDto(
            id: (string) ($row['id'] ?? ''),
            tenantId: (string) ($row['tenant_id'] ?? ''),
            status: (string) ($row['status'] ?? ''),
            customerEmail: $this->decryptEmail((string) ($row['customer_email'] ?? '')),
            lineCount: (int) ($row['line_count'] ?? 0),
            totalAmount: (int) ($row['total_amount'] ?? 0),
            totalCurrency: (string) ($row['total_currency'] ?? 'USD'),
            discountAmount: null !== $row['discount_amount'] ? (int) $row['discount_amount'] : null,
            couponCode: isset($row['coupon_code']) ? (string) $row['coupon_code'] : null,
            createdAt: (string) ($row['created_at'] ?? ''),
            updatedAt: (string) ($row['updated_at'] ?? ''),
        );
    }

    private function decryptEmail(string $encrypted): string
    {
        if ('' === $encrypted) {
            return '';
        }

        try {
            return $this->encryptionService->decrypt($encrypted);
        } catch (\Throwable) {
            return '[encrypted]';
        }
    }
}
