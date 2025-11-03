<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Tax\Presentation\Api\Processor\CalculateTaxProcessor;
use Symfony\Component\Serializer\Annotation\Groups;

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
            normalizationContext: ['groups' => ['tax_calculation:read']],
            denormalizationContext: ['groups' => ['tax_calculation:write']],
            processor: CalculateTaxProcessor::class
        ),
    ]
)]
final class TaxCalculationResource
{
    // Input fields
    #[Groups(['tax_calculation:write'])]
    public ?int $amountInCents = null;

    #[Groups(['tax_calculation:write'])]
    public ?string $countryCode = null;

    #[Groups(['tax_calculation:write'])]
    public ?string $regionCode = null;

    #[Groups(['tax_calculation:write'])]
    public ?string $tenantId = null;

    // Output fields
    #[Groups(['tax_calculation:read'])]
    public ?int $taxAmount = null;

    #[Groups(['tax_calculation:read'])]
    public ?float $taxRate = null;

    #[Groups(['tax_calculation:read'])]
    public ?string $jurisdiction = null;

    #[Groups(['tax_calculation:read'])]
    public ?string $taxRuleId = null;

    #[Groups(['tax_calculation:read'])]
    public ?string $taxRuleName = null;
}
