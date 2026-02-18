<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Domain\Model\BillingAddress;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Invoice\Domain\Model\InvoiceStatus;
use App\Invoice\Infrastructure\ApiPlatform\State\CancelInvoiceProcessor;
use App\Invoice\Infrastructure\ApiPlatform\State\CreateInvoiceProcessor;
use App\Invoice\Infrastructure\ApiPlatform\State\InvoicePdfProvider;
use App\Invoice\Infrastructure\ApiPlatform\State\IssueInvoiceProcessor;
use App\Invoice\Infrastructure\ApiPlatform\State\MarkInvoicePaidProcessor;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/invoices/{id}',
            security: "is_granted('ROLE_USER')",
        ),
        new GetCollection(
            uriTemplate: '/invoices',
            security: "is_granted('ROLE_USER')",
        ),
        new Post(
            uriTemplate: '/invoices',
            processor: CreateInvoiceProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')",
            denormalizationContext: ['groups' => ['invoice:write']],
        ),
        new Post(
            uriTemplate: '/invoices/{id}/issue',
            processor: IssueInvoiceProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')",
            denormalizationContext: ['groups' => ['invoice:write']],
        ),
        new Post(
            uriTemplate: '/invoices/{id}/paid',
            processor: MarkInvoicePaidProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')",
            denormalizationContext: ['groups' => ['invoice:write']],
        ),
        new Post(
            uriTemplate: '/invoices/{id}/cancel',
            processor: CancelInvoiceProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')",
        ),
        new Get(
            uriTemplate: '/invoices/{id}/pdf',
            provider: InvoicePdfProvider::class,
            security: "is_granted('ROLE_USER')",
            formats: ['pdf' => ['application/pdf']],
        ),
    ],
    normalizationContext: ['groups' => ['invoice:read']],
    paginationEnabled: true,
    paginationItemsPerPage: 30,
)]
#[ORM\Entity]
#[ORM\Table(name: 'invoices')]
// Performance indexes for queries
#[ORM\Index(name: 'idx_invoices_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_invoices_order_id', columns: ['order_id'])]
#[ORM\Index(name: 'idx_invoices_customer_id', columns: ['customer_id'])]
#[ORM\Index(name: 'idx_invoices_invoice_number', columns: ['invoice_number'])]
#[ORM\Index(name: 'idx_invoices_status', columns: ['status'])]
#[ORM\Index(name: 'idx_invoices_issue_date', columns: ['issue_date'])]
#[ORM\Index(name: 'idx_invoices_due_date', columns: ['due_date'])]
#[ORM\Index(name: 'idx_invoices_tenant_status', columns: ['tenant_id', 'status'])]
#[ORM\Index(name: 'idx_invoices_tenant_customer', columns: ['tenant_id', 'customer_id'])]
class InvoiceEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['invoice:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'tenant_id')]
    #[Groups(['invoice:read'])]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 36, name: 'order_id')]
    #[Groups(['invoice:read'])]
    private string $orderId;

    #[ORM\Column(type: 'string', length: 36, name: 'customer_id')]
    #[Groups(['invoice:read'])]
    private string $customerId;

    #[ORM\Column(type: 'string', length: 20, nullable: true, name: 'invoice_number')]
    #[Groups(['invoice:read'])]
    private ?string $invoiceNumber = null;

    #[ORM\Column(type: 'string', length: 20)]
    #[Groups(['invoice:read'])]
    private string $status;

    /** @var array{name: string, addressLine1: string, addressLine2: string|null, city: string, postalCode: string, country: string, vatNumber: string|null} */
    #[ORM\Column(type: 'encrypted_json', name: 'billing_address')]
    #[Groups(['invoice:read'])]
    private array $billingAddress;

    #[ORM\Column(type: 'integer', name: 'subtotal_amount')]
    #[Groups(['invoice:read'])]
    private int $subtotalAmount;

    #[ORM\Column(type: 'string', length: 3, name: 'subtotal_currency')]
    #[Groups(['invoice:read'])]
    private string $subtotalCurrency;

    #[ORM\Column(type: 'integer', name: 'tax_amount')]
    #[Groups(['invoice:read'])]
    private int $taxAmount;

    #[ORM\Column(type: 'string', length: 3, name: 'tax_currency')]
    #[Groups(['invoice:read'])]
    private string $taxCurrency;

    #[ORM\Column(type: 'integer', name: 'total_amount')]
    #[Groups(['invoice:read'])]
    private int $totalAmount;

    #[ORM\Column(type: 'string', length: 3, name: 'total_currency')]
    #[Groups(['invoice:read'])]
    private string $totalCurrency;

    /** @var array<string, int> */
    #[ORM\Column(type: 'json', name: 'tax_breakdown')]
    #[Groups(['invoice:read'])]
    private array $taxBreakdown;

    #[ORM\Column(type: 'boolean', name: 'is_reverse_charge')]
    #[Groups(['invoice:read'])]
    private bool $isReverseCharge;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'issue_date')]
    #[Groups(['invoice:read'])]
    private ?\DateTimeImmutable $issueDate = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'due_date')]
    #[Groups(['invoice:read'])]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'paid_date')]
    #[Groups(['invoice:read'])]
    private ?\DateTimeImmutable $paidDate = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true, name: 'pdf_path')]
    #[Groups(['invoice:read'])]
    private ?string $pdfPath = null;

    #[ORM\Column(type: 'string', length: 36, nullable: true, name: 'credited_invoice_id')]
    #[Groups(['invoice:read'])]
    private ?string $creditedInvoiceId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    #[Groups(['invoice:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    #[Groups(['invoice:read'])]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, InvoiceLineEntity>
     */
    #[ORM\OneToMany(targetEntity: InvoiceLineEntity::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['invoice:read'])]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    /**
     * Create entity from domain model.
     */
    public static function fromDomainModel(Invoice $invoice): self
    {
        $entity = new self();
        $entity->id = $invoice->id()->toString();
        $entity->tenantId = $invoice->tenantId()->toString();
        $entity->orderId = $invoice->orderId()->toString();
        $entity->customerId = $invoice->customerId()->toString();
        $entity->invoiceNumber = $invoice->number()?->toString();
        $entity->status = $invoice->status()->value;
        $entity->billingAddress = $invoice->billingAddress()->toArray();
        $entity->subtotalAmount = $invoice->subtotal()->getAmount();
        $entity->subtotalCurrency = $invoice->subtotal()->getCurrency()->getCurrencyCode();
        $entity->taxAmount = $invoice->taxAmount()->getAmount();
        $entity->taxCurrency = $invoice->taxAmount()->getCurrency()->getCurrencyCode();
        $entity->totalAmount = $invoice->total()->getAmount();
        $entity->totalCurrency = $invoice->total()->getCurrency()->getCurrencyCode();
        $entity->taxBreakdown = $invoice->taxBreakdown();
        $entity->isReverseCharge = $invoice->isReverseCharge();
        $entity->issueDate = $invoice->issueDate();
        $entity->dueDate = $invoice->dueDate();
        $entity->paidDate = $invoice->paidDate();
        $entity->pdfPath = $invoice->pdfPath();
        $entity->creditedInvoiceId = $invoice->creditedInvoiceId()?->toString();
        $entity->notes = $invoice->notes();
        $entity->createdAt = $invoice->createdAt();
        $entity->updatedAt = $invoice->updatedAt();

        // Create line entities
        foreach ($invoice->lines() as $line) {
            $lineEntity = InvoiceLineEntity::fromDomainModel($line, $entity->id);
            $lineEntity->setInvoice($entity);
            $entity->lines->add($lineEntity);
        }

        return $entity;
    }

    /**
     * Update this entity's properties from a domain model.
     * Used for updating existing entities without creating new instances (Doctrine 3.x compatibility).
     */
    public function updateFromDomainModel(Invoice $invoice): void
    {
        // Note: id, tenantId, orderId, customerId should never change
        $this->invoiceNumber = $invoice->number()?->toString();
        $this->status = $invoice->status()->value;
        $this->billingAddress = $invoice->billingAddress()->toArray();
        $this->subtotalAmount = $invoice->subtotal()->getAmount();
        $this->subtotalCurrency = $invoice->subtotal()->getCurrency()->getCurrencyCode();
        $this->taxAmount = $invoice->taxAmount()->getAmount();
        $this->taxCurrency = $invoice->taxAmount()->getCurrency()->getCurrencyCode();
        $this->totalAmount = $invoice->total()->getAmount();
        $this->totalCurrency = $invoice->total()->getCurrency()->getCurrencyCode();
        $this->taxBreakdown = $invoice->taxBreakdown();
        $this->isReverseCharge = $invoice->isReverseCharge();
        $this->issueDate = $invoice->issueDate();
        $this->dueDate = $invoice->dueDate();
        $this->paidDate = $invoice->paidDate();
        $this->pdfPath = $invoice->pdfPath();
        $this->creditedInvoiceId = $invoice->creditedInvoiceId()?->toString();
        $this->notes = $invoice->notes();
        $this->updatedAt = $invoice->updatedAt();

        // Update lines: clear existing and add new ones
        $this->lines->clear();
        foreach ($invoice->lines() as $line) {
            $lineEntity = InvoiceLineEntity::fromDomainModel($line, $this->id);
            $lineEntity->setInvoice($this);
            $this->lines->add($lineEntity);
        }
    }

    /**
     * Convert entity to domain model.
     */
    public function toDomainModel(): Invoice
    {
        // Convert line entities to domain models
        $lines = array_map(
            fn (InvoiceLineEntity $lineEntity) => $lineEntity->toDomainModel(),
            $this->lines->toArray()
        );

        // Reconstruct BillingAddress from JSON
        $billingAddress = BillingAddress::create(
            name: $this->billingAddress['name'],
            addressLine1: $this->billingAddress['addressLine1'],
            addressLine2: $this->billingAddress['addressLine2'],
            city: $this->billingAddress['city'],
            postalCode: $this->billingAddress['postalCode'],
            country: $this->billingAddress['country'],
            vatNumber: $this->billingAddress['vatNumber']
        );

        return Invoice::reconstituteFromPersistence(
            id: InvoiceId::fromString($this->id),
            tenantId: TenantId::fromString($this->tenantId),
            orderId: OrderId::fromString($this->orderId),
            customerId: CustomerId::fromString($this->customerId),
            number: $this->invoiceNumber ? InvoiceNumber::fromString($this->invoiceNumber) : null,
            status: InvoiceStatus::from($this->status),
            billingAddress: $billingAddress,
            lines: $lines,
            subtotal: Money::fromScalars($this->subtotalAmount, $this->subtotalCurrency),
            taxAmount: Money::fromScalars($this->taxAmount, $this->taxCurrency),
            total: Money::fromScalars($this->totalAmount, $this->totalCurrency),
            taxBreakdown: $this->taxBreakdown,
            isReverseCharge: $this->isReverseCharge,
            issueDate: $this->issueDate,
            dueDate: $this->dueDate,
            paidDate: $this->paidDate,
            pdfPath: $this->pdfPath,
            creditedInvoiceId: $this->creditedInvoiceId ? InvoiceId::fromString($this->creditedInvoiceId) : null,
            notes: $this->notes,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt
        );
    }

    // Getters

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return array{name: string, addressLine1: string, addressLine2: string|null, city: string, postalCode: string, country: string, vatNumber: string|null}
     */
    public function getBillingAddress(): array
    {
        return $this->billingAddress;
    }

    public function getSubtotalAmount(): int
    {
        return $this->subtotalAmount;
    }

    public function getSubtotalCurrency(): string
    {
        return $this->subtotalCurrency;
    }

    public function getTaxAmount(): int
    {
        return $this->taxAmount;
    }

    public function getTaxCurrency(): string
    {
        return $this->taxCurrency;
    }

    public function getTotalAmount(): int
    {
        return $this->totalAmount;
    }

    public function getTotalCurrency(): string
    {
        return $this->totalCurrency;
    }

    /**
     * @return array<string, int>
     */
    public function getTaxBreakdown(): array
    {
        return $this->taxBreakdown;
    }

    public function isReverseCharge(): bool
    {
        return $this->isReverseCharge;
    }

    public function getIssueDate(): ?\DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function getPaidDate(): ?\DateTimeImmutable
    {
        return $this->paidDate;
    }

    public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function getCreditedInvoiceId(): ?string
    {
        return $this->creditedInvoiceId;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, InvoiceLineEntity>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }
}
