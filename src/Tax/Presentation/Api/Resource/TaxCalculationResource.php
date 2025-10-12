<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Tax\Presentation\Api\Provider\CalculateTaxProvider;

/**
 * Tax Calculation API Resource
 *
 * Represents a tax calculation request and response.
 */
#[ApiResource(
    shortName: 'TaxCalculation',
    operations: [
        new Post(
            uriTemplate: '/tax_calculations',
            provider: CalculateTaxProvider::class,
            read: false
        ),
    ]
)]
final class TaxCalculationResource
{
    // Input fields
    #[ApiProperty(writable: true, readable: false)]
    public ?int $amountInCents = null;

    #[ApiProperty(writable: true, readable: false)]
    public ?string $countryCode = null;

    #[ApiProperty(writable: true, readable: false)]
    public ?string $regionCode = null;

    #[ApiProperty(writable: true, readable: false)]
    public ?string $tenantId = null;

    // Output fields
    #[ApiProperty(readable: true, writable: false)]
    public ?int $taxAmount = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?float $taxRate = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $jurisdiction = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $taxRuleId = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $taxRuleName = null;
}
