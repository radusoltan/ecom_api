<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ProductId;
use App\Invoice\Domain\Model\InvoiceLine;
use App\Invoice\Domain\Model\InvoiceLineId;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'invoice_lines')]
#[ORM\Index(name: 'idx_invoice_lines_invoice_id', columns: ['invoice_id'])]
#[ORM\Index(name: 'idx_invoice_lines_product_id', columns: ['product_id'])]
class InvoiceLineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['invoice:read'])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: InvoiceEntity::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'invoice_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private InvoiceEntity $invoice;

    #[ORM\Column(type: 'string', length: 36, nullable: true, name: 'product_id')]
    #[Groups(['invoice:read'])]
    private ?string $productId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $sku = null;

    #[ORM\Column(type: 'string', length: 500)]
    #[Groups(['invoice:read'])]
    private string $description;

    #[ORM\Column(type: 'integer')]
    #[Groups(['invoice:read'])]
    private int $quantity;

    #[ORM\Column(type: 'integer', name: 'unit_price_amount')]
    #[Groups(['invoice:read'])]
    private int $unitPriceAmount;

    #[ORM\Column(type: 'string', length: 3, name: 'unit_price_currency')]
    #[Groups(['invoice:read'])]
    private string $unitPriceCurrency;

    #[ORM\Column(type: 'float', name: 'tax_rate')]
    #[Groups(['invoice:read'])]
    private float $taxRate;

    #[ORM\Column(type: 'integer', name: 'tax_amount')]
    #[Groups(['invoice:read'])]
    private int $taxAmount;

    #[ORM\Column(type: 'string', length: 3, name: 'tax_currency')]
    #[Groups(['invoice:read'])]
    private string $taxCurrency;

    #[ORM\Column(type: 'integer', name: 'line_total_amount')]
    #[Groups(['invoice:read'])]
    private int $lineTotalAmount;

    #[ORM\Column(type: 'string', length: 3, name: 'line_total_currency')]
    #[Groups(['invoice:read'])]
    private string $lineTotalCurrency;

    #[ORM\Column(type: 'integer')]
    #[Groups(['invoice:read'])]
    private int $position;

    /**
     * Create entity from domain model.
     */
    public static function fromDomainModel(InvoiceLine $line, string $invoiceId): self
    {
        $entity = new self();
        $entity->id = $line->id()->toString();
        $entity->productId = $line->productId()?->toString();
        $entity->sku = $line->sku();
        $entity->description = $line->description();
        $entity->quantity = $line->quantity();
        $entity->unitPriceAmount = $line->unitPrice()->getAmount();
        $entity->unitPriceCurrency = $line->unitPrice()->getCurrency()->getCurrencyCode();
        $entity->taxRate = $line->taxRate();
        $entity->taxAmount = $line->taxAmount()->getAmount();
        $entity->taxCurrency = $line->taxAmount()->getCurrency()->getCurrencyCode();
        $entity->lineTotalAmount = $line->lineTotal()->getAmount();
        $entity->lineTotalCurrency = $line->lineTotal()->getCurrency()->getCurrencyCode();
        $entity->position = $line->position();

        return $entity;
    }

    /**
     * Convert entity to domain model.
     */
    public function toDomainModel(): InvoiceLine
    {
        return InvoiceLine::reconstituteFromPersistence(
            id: InvoiceLineId::fromString($this->id),
            productId: $this->productId ? ProductId::fromString($this->productId) : null,
            sku: $this->sku,
            description: $this->description,
            quantity: $this->quantity,
            unitPrice: Money::fromScalars($this->unitPriceAmount, $this->unitPriceCurrency),
            taxRate: $this->taxRate,
            taxAmount: Money::fromScalars($this->taxAmount, $this->taxCurrency),
            lineTotal: Money::fromScalars($this->lineTotalAmount, $this->lineTotalCurrency),
            position: $this->position
        );
    }

    // Getters

    public function getId(): string
    {
        return $this->id;
    }

    public function getInvoice(): InvoiceEntity
    {
        return $this->invoice;
    }

    public function setInvoice(InvoiceEntity $invoice): void
    {
        $this->invoice = $invoice;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPriceAmount(): int
    {
        return $this->unitPriceAmount;
    }

    public function getUnitPriceCurrency(): string
    {
        return $this->unitPriceCurrency;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function getTaxAmount(): int
    {
        return $this->taxAmount;
    }

    public function getTaxCurrency(): string
    {
        return $this->taxCurrency;
    }

    public function getLineTotalAmount(): int
    {
        return $this->lineTotalAmount;
    }

    public function getLineTotalCurrency(): string
    {
        return $this->lineTotalCurrency;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
