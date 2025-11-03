<?php

declare(strict_types=1);

namespace App\Cart\Infrastructure\Persistence\Doctrine\Entity;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\CartStatus;
use App\Cart\Domain\Model\SessionId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'carts')]
#[ORM\Index(name: 'idx_carts_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_carts_customer_id', columns: ['customer_id'])]
#[ORM\Index(name: 'idx_carts_session_id', columns: ['session_id'])]
#[ORM\Index(name: 'idx_carts_status', columns: ['status'])]
#[ORM\Index(name: 'idx_carts_updated_at', columns: ['updated_at'])]
#[ORM\Index(name: 'idx_carts_tenant_customer', columns: ['tenant_id', 'customer_id'])]
#[ORM\Index(name: 'idx_carts_tenant_session', columns: ['tenant_id', 'session_id'])]
class CartEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 36, nullable: true, name: 'customer_id')]
    private ?string $customerId = null;

    #[ORM\Column(type: 'string', length: 36, nullable: true, name: 'session_id')]
    private ?string $sessionId = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    /**
     * @var Collection<int, CartItemEntity>
     */
    #[ORM\OneToMany(
        targetEntity: CartItemEntity::class,
        mappedBy: 'cartId',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $items;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public static function fromDomainModel(Cart $cart): self
    {
        $entity = new self();
        $entity->id = $cart->id()->toString();
        $entity->tenantId = $cart->tenantId()->toString();
        $entity->customerId = $cart->customerId()?->toString();
        $entity->sessionId = $cart->sessionId()?->toString();
        $entity->status = $cart->status()->value();
        $entity->createdAt = $cart->createdAt();
        $entity->updatedAt = $cart->updatedAt();

        // Convert domain cart items to entities
        foreach ($cart->items() as $item) {
            $itemEntity = CartItemEntity::fromDomainModel($item, $entity->id);
            $entity->items->add($itemEntity);
        }

        return $entity;
    }

    public function toDomainModel(): Cart
    {
        // Convert entity items to domain items
        $domainItems = array_map(
            fn(CartItemEntity $itemEntity) => $itemEntity->toDomainModel(),
            $this->items->toArray()
        );

        return Cart::reconstituteFromPersistence(
            CartId::fromString($this->id),
            TenantId::fromString($this->tenantId),
            $this->customerId !== null ? CustomerId::fromString($this->customerId) : null,
            $this->sessionId !== null ? SessionId::fromString($this->sessionId) : null,
            CartStatus::from($this->status),
            $domainItems,
            $this->createdAt,
            $this->updatedAt
        );
    }

    public function updateFromDomainModel(Cart $cart): void
    {
        // ID and tenantId should never change
        $this->customerId = $cart->customerId()?->toString();
        $this->sessionId = $cart->sessionId()?->toString();
        $this->status = $cart->status()->value();
        $this->updatedAt = $cart->updatedAt();

        // Update items collection
        // Clear existing items
        $this->items->clear();

        // Add current items from domain model
        foreach ($cart->items() as $item) {
            $itemEntity = CartItemEntity::fromDomainModel($item, $this->id);
            $this->items->add($itemEntity);
        }
    }

    // Getters for Doctrine

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return Collection<int, CartItemEntity>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}