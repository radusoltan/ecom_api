<?php

declare(strict_types=1);

namespace App\Tenant\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Tenant\Presentation\Api\Processor\CreateFeatureFlagProcessor;
use App\Tenant\Presentation\Api\Processor\DeleteFeatureFlagProcessor;
use App\Tenant\Presentation\Api\Processor\ToggleFeatureFlagProcessor;
use App\Tenant\Presentation\Api\Provider\FeatureFlagCollectionProvider;
use App\Tenant\Presentation\Api\Provider\FeatureFlagItemProvider;

#[ApiResource(
    shortName: 'FeatureFlag',
    operations: [
        new GetCollection(
            uriTemplate: '/feature-flags',
            security: "is_granted('ROLE_ADMIN')",
            provider: FeatureFlagCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/feature-flags/{id}',
            security: "is_granted('ROLE_ADMIN')",
            provider: FeatureFlagItemProvider::class,
        ),
        new Post(
            uriTemplate: '/feature-flags',
            security: "is_granted('ROLE_ADMIN')",
            processor: CreateFeatureFlagProcessor::class,
        ),
        new Patch(
            uriTemplate: '/feature-flags/{id}/toggle',
            security: "is_granted('ROLE_ADMIN')",
            provider: FeatureFlagItemProvider::class,
            processor: ToggleFeatureFlagProcessor::class,
        ),
        new Delete(
            uriTemplate: '/feature-flags/{id}',
            security: "is_granted('ROLE_ADMIN')",
            provider: FeatureFlagItemProvider::class,
            processor: DeleteFeatureFlagProcessor::class,
        ),
    ],
)]
final class FeatureFlagResource
{
    public ?string $id = null;
    public ?string $tenantId = null;
    public ?string $featureName = null;
    public ?bool $enabled = null;
    /** @var array<string, mixed> */
    public array $configuration = [];
    public ?string $description = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
}
