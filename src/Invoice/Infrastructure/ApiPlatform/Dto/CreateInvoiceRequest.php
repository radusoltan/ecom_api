<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\ApiPlatform\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for creating a draft invoice.
 *
 * Used by POST /api/invoices endpoint to create a new invoice in DRAFT status.
 *
 * Example payload:
 * ```json
 * {
 *   "orderId": "01234567-89ab-cdef-0123-456789abcdef",
 *   "customerId": "01234567-89ab-cdef-0123-456789abcdef",
 *   "billingAddress": {
 *     "name": "John Doe",
 *     "addressLine1": "123 Main St",
 *     "addressLine2": "Apt 4B",
 *     "city": "New York",
 *     "postalCode": "10001",
 *     "country": "US",
 *     "vatNumber": "US123456789"
 *   },
 *   "lines": [
 *     {
 *       "description": "Product Name",
 *       "quantity": 2,
 *       "unitPriceAmount": 1999,
 *       "unitPriceCurrency": "USD",
 *       "taxRate": 0.19,
 *       "productId": "01234567-89ab-cdef-0123-456789abcdef",
 *       "sku": "PROD-001"
 *     }
 *   ],
 *   "isReverseCharge": false,
 *   "notes": "Optional notes"
 * }
 * ```
 */
final class CreateInvoiceRequest
{
    #[Groups(['invoice:write'])]
    #[Assert\NotBlank(message: 'Order ID is required')]
    #[Assert\Uuid(message: 'Order ID must be a valid UUID')]
    public string $orderId;

    #[Groups(['invoice:write'])]
    #[Assert\NotBlank(message: 'Customer ID is required')]
    #[Assert\Uuid(message: 'Customer ID must be a valid UUID')]
    public string $customerId;

    /**
     * @var array{name: string, addressLine1: string, addressLine2: string|null, city: string, postalCode: string, country: string, vatNumber: string|null}
     */
    #[Groups(['invoice:write'])]
    #[Assert\NotBlank(message: 'Billing address is required')]
    #[Assert\Collection(
        fields: [
            'name' => [
                new Assert\NotBlank(message: 'Name is required'),
                new Assert\Length(max: 255),
            ],
            'addressLine1' => [
                new Assert\NotBlank(message: 'Address line 1 is required'),
                new Assert\Length(max: 255),
            ],
            'addressLine2' => [
                new Assert\Length(max: 255),
            ],
            'city' => [
                new Assert\NotBlank(message: 'City is required'),
                new Assert\Length(max: 100),
            ],
            'postalCode' => [
                new Assert\NotBlank(message: 'Postal code is required'),
                new Assert\Length(max: 20),
            ],
            'country' => [
                new Assert\NotBlank(message: 'Country is required'),
                new Assert\Country(message: 'Invalid country code'),
            ],
            'vatNumber' => [
                new Assert\Length(max: 50),
            ],
        ],
        allowExtraFields: false
    )]
    public array $billingAddress;

    /**
     * @var InvoiceLineRequest[]
     */
    #[Groups(['invoice:write'])]
    #[Assert\NotBlank(message: 'At least one line item is required')]
    #[Assert\Count(min: 1, minMessage: 'At least one line item is required')]
    #[Assert\Valid]
    public array $lines = [];

    #[Groups(['invoice:write'])]
    public bool $isReverseCharge = false;

    #[Groups(['invoice:write'])]
    #[Assert\Length(max: 5000, maxMessage: 'Notes cannot exceed 5000 characters')]
    public ?string $notes = null;
}
