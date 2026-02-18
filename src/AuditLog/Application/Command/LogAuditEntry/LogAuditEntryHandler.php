<?php

declare(strict_types=1);

namespace App\AuditLog\Application\Command\LogAuditEntry;

use App\AuditLog\Domain\Model\AuditLogEntry;
use App\AuditLog\Domain\Repository\AuditLogRepositoryInterface;
use App\AuditLog\Domain\ValueObject\ActionType;
use App\AuditLog\Domain\ValueObject\ResourceType;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class LogAuditEntryHandler
{
    public function __construct(
        private AuditLogRepositoryInterface $repository,
    ) {
    }

    public function __invoke(LogAuditEntry $command): void
    {
        $entry = AuditLogEntry::log(
            tenantId: TenantId::fromString($command->tenantId),
            userId: $command->userId ? UserId::fromString($command->userId) : null,
            actionType: ActionType::fromString($command->actionType),
            resourceType: ResourceType::fromString($command->resourceType),
            resourceId: $command->resourceId,
            metadata: $command->metadata,
            ipAddress: $command->ipAddress,
            userAgent: $command->userAgent
        );

        $this->repository->save($entry);
    }
}
