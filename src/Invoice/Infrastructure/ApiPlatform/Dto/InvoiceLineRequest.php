<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\ApiPlatform\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for invoice line items.
 *
 * Represents a single line item in an invoice with pricing and tax information.
 */
final class InvoiceLineRequest
{
    #[Groups(['invoice:write'])]
    #[Assert\NotBlank(message: 'Description is required')]
    #[Assert\Length(max: 500, maxMessage: 'Description cannot exceed 500 characters')]
    public string $description;

    #[Groups(['invoice:write'])]
    #[Assert\NotBlank(message: 'Quantity is required')]
    #[Assert\GreaterThan(0, message: 'Quantity must be greater than 0')]
    public int $quantity;

    #[Groups(['invoice:write'])]
    #[Assert\NotBlank(message: 'Unit price amount is required')]
    #[Assert\GreaterThan(0, message: 'Unit price must be greater than 0')]
    public int $unitPriceAmount;

    #[Groups(['invoice:write'])]
    #[Assert\NotBlank(message: 'Unit price currency is required')]
    #[Assert\Currency(message: 'Invalid currency code')]
    public string $unitPriceCurrency;

    #[Groups(['invoice:write'])]
    #[Assert\NotBlank(message: 'Tax rate is required')]
    #[Assert\GreaterThanOrEqual(0, message: 'Tax rate must be 0 or greater')]
    #[Assert\LessThanOrEqual(100, message: 'Tax rate must be 100 or less')]
    public float $taxRate;

    #[Groups(['invoice:write'])]
    #[Assert\Uuid(message: 'Product ID must be a valid UUID')]
    public ?string $productId = null;

    #[Groups(['invoice:write'])]
    #[Assert\Length(max: 255, maxMessage: 'SKU cannot exceed 255 characters')]
    public ?string $sku = null;
}
