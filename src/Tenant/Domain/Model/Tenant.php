<?php

declare(strict_types=1);

namespace App\Tenant\Domain\Model;

use DateTimeImmutable;
use DomainException;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Email;
use App\Tenant\Domain\Event\TenantActivated;
use App\Tenant\Domain\Event\TenantCreated;
use App\Tenant\Domain\Event\TenantDeactivated;
use App\Tenant\Domain\Event\TenantUpdated;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Tenant\Domain\ValueObject\TenantName;
use App\Tenant\Domain\ValueObject\TenantStatus;

final class Tenant extends AggregateRoot
{
    private function __construct(
        private readonly TenantId $id,
        private TenantName $name,
        private Email $ownerEmail,
        private TenantStatus $status,
        private readonly DateTimeImmutable $createdAt
    ) {
        $this->recordEvent(new TenantCreated($this->id, $this->name, $this->ownerEmail));
    }

    public static function create(TenantName $name, Email $ownerEmail): self
    {
        return new self(
            TenantId::generate(),
            $name,
            $ownerEmail,
            TenantStatus::active(),
            new DateTimeImmutable()
        );
    }

    public static function fromPersistence(
        TenantId $id,
        TenantName $name,
        Email $ownerEmail,
        TenantStatus $status,
        DateTimeImmutable $createdAt
    ): self {
        $instance = new self($id, $name, $ownerEmail, $status, $createdAt);
        $instance->clearEvents();

        return $instance;
    }

    public function activate(): void
    {
        if ($this->status->isActive()) {
            throw new DomainException('Tenant is already active');
        }
        $this->status = TenantStatus::active();
        $this->recordEvent(new TenantActivated($this->id));
    }

    public function deactivate(): void
    {
        if (!$this->status->isActive()) {
            throw new DomainException('Tenant is not active');
        }
        $this->status = TenantStatus::inactive();
        $this->recordEvent(new TenantDeactivated($this->id));
    }

    public function update(TenantName $name, Email $ownerEmail): void
    {
        $this->name = $name;
        $this->ownerEmail = $ownerEmail;
        $this->recordEvent(new TenantUpdated($this->id, $this->name, $this->ownerEmail));
    }

    // Getters
    public function id(): TenantId
    {
        return $this->id;
    }

    public function name(): TenantName
    {
        return $this->name;
    }

    public function ownerEmail(): Email
    {
        return $this->ownerEmail;
    }

    public function status(): TenantStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
