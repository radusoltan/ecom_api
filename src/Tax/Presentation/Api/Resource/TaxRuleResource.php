<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Tax\Presentation\Api\Processor\CreateTaxRuleProcessor;
use App\Tax\Presentation\Api\Processor\DeactivateTaxRuleProcessor;
use App\Tax\Presentation\Api\Processor\UpdateTaxRuleProcessor;
use App\Tax\Presentation\Api\Provider\TaxRuleCollectionProvider;
use App\Tax\Presentation\Api\Provider\TaxRuleItemProvider;

/**
 * Tax Rule API Resource.
 *
 * Represents tax rules for multi-jurisdiction tax calculation.
 */
#[ApiResource(
    shortName: 'TaxRule',
    operations: [
        new Get(
            uriTemplate: '/tax_rules/{id}',
            provider: TaxRuleItemProvider::class
        ),
        new GetCollection(
            uriTemplate: '/tax_rules',
            provider: TaxRuleCollectionProvider::class
        ),
        new Post(
            uriTemplate: '/tax_rules',
            processor: CreateTaxRuleProcessor::class
        ),
        new Patch(
            uriTemplate: '/tax_rules/{id}',
            processor: UpdateTaxRuleProcessor::class
        ),
        new Patch(
            uriTemplate: '/tax_rules/{id}/deactivate',
            processor: DeactivateTaxRuleProcessor::class
        ),
    ],
    paginationEnabled: true,
    paginationItemsPerPage: 30
)]
final class TaxRuleResource
{
    #[ApiProperty(identifier: true)]
    public ?string $id = null;

    public ?string $tenantId = null;

    public ?string $name = null;

    public ?string $countryCode = null;

    public ?string $regionCode = null;

    public ?float $ratePercentage = null;

    public ?bool $isActive = null;

    public ?\DateTimeImmutable $createdAt = null;

    public ?\DateTimeImmutable $updatedAt = null;
}
