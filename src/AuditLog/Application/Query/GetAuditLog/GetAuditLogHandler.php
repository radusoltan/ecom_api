<?php

declare(strict_types=1);

namespace App\AuditLog\Application\Query\GetAuditLog;

use App\AuditLog\Domain\Model\AuditLogEntry;
use App\AuditLog\Domain\Repository\AuditLogRepositoryInterface;
use App\AuditLog\Domain\ValueObject\ActionType;
use App\AuditLog\Domain\ValueObject\ResourceType;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAuditLogHandler
{
    public function __construct(
        private AuditLogRepositoryInterface $repository,
    ) {
    }

    /**
     * @return list<AuditLogEntry>
     */
    public function __invoke(GetAuditLog $query): array
    {
        $filters = [
            'limit' => $query->limit,
            'offset' => $query->offset,
        ];

        if ($query->userId) {
            $filters['userId'] = UserId::fromString($query->userId);
        }

        if ($query->actionType) {
            $filters['actionType'] = ActionType::fromString($query->actionType);
        }

        if ($query->resourceType) {
            $filters['resourceType'] = ResourceType::fromString($query->resourceType);
        }

        if ($query->resourceId) {
            $filters['resourceId'] = $query->resourceId;
        }

        if ($query->fromDate) {
            $filters['fromDate'] = new \DateTimeImmutable($query->fromDate);
        }

        if ($query->toDate) {
            $filters['toDate'] = new \DateTimeImmutable($query->toDate);
        }

        return $this->repository->findByTenant(
            TenantId::fromString($query->tenantId),
            $filters
        );
    }
}
