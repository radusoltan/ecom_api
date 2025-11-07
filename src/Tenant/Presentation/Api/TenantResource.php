<?php

declare(strict_types=1);

namespace App\Tenant\Presentation\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Tenant\Presentation\Api\Processor\ActivateTenantProcessor;
use App\Tenant\Presentation\Api\Processor\CreateTenantProcessor;
use App\Tenant\Presentation\Api\Processor\DeactivateTenantProcessor;
use App\Tenant\Presentation\Api\Processor\DeleteTenantProcessor;
use App\Tenant\Presentation\Api\Processor\DisableLocaleProcessor;
use App\Tenant\Presentation\Api\Processor\EnableLocaleProcessor;
use App\Tenant\Presentation\Api\Processor\ReactivateTenantProcessor;
use App\Tenant\Presentation\Api\Processor\SetDefaultLocaleProcessor;
use App\Tenant\Presentation\Api\Processor\SuspendTenantProcessor;
use App\Tenant\Presentation\Api\Processor\UpdateTenantProcessor;
use App\Tenant\Presentation\Api\Provider\TenantCollectionProvider;
use App\Tenant\Presentation\Api\Provider\TenantItemProvider;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Tenant',
    operations: [
        new GetCollection(
            uriTemplate: '/tenants',
            normalizationContext: ['groups' => ['tenant:read']],
            provider: TenantCollectionProvider::class
        ),
        new Get(
            uriTemplate: '/tenants/{id}',
            normalizationContext: ['groups' => ['tenant:read']],
            provider: TenantItemProvider::class
        ),
        new Post(
            uriTemplate: '/tenants',
            normalizationContext: ['groups' => ['tenant:read']],
            denormalizationContext: ['groups' => ['tenant:create']],
            processor: CreateTenantProcessor::class
        ),
        new Put(
            uriTemplate: '/tenants/{id}',
            normalizationContext: ['groups' => ['tenant:read']],
            denormalizationContext: ['groups' => ['tenant:update']],
            provider: TenantItemProvider::class,
            processor: UpdateTenantProcessor::class
        ),
        new Delete(
            uriTemplate: '/tenants/{id}',
            provider: TenantItemProvider::class,
            processor: DeleteTenantProcessor::class
        ),
        new Patch(
            uriTemplate: '/tenants/{id}/activate',
            normalizationContext: ['groups' => ['tenant:read']],
            denormalizationContext: ['groups' => ['tenant:activate']],
            provider: TenantItemProvider::class,
            processor: ActivateTenantProcessor::class
        ),
        new Patch(
            uriTemplate: '/tenants/{id}/deactivate',
            normalizationContext: ['groups' => ['tenant:read']],
            denormalizationContext: ['groups' => ['tenant:deactivate']],
            provider: TenantItemProvider::class,
            processor: DeactivateTenantProcessor::class
        ),
        new Patch(
            uriTemplate: '/tenants/{id}/suspend',
            normalizationContext: ['groups' => ['tenant:read']],
            denormalizationContext: ['groups' => ['tenant:suspend']],
            provider: TenantItemProvider::class,
            processor: SuspendTenantProcessor::class
        ),
        new Patch(
            uriTemplate: '/tenants/{id}/reactivate',
            normalizationContext: ['groups' => ['tenant:read']],
            denormalizationContext: ['groups' => ['tenant:reactivate']],
            provider: TenantItemProvider::class,
            processor: ReactivateTenantProcessor::class
        ),
        new Patch(
            uriTemplate: '/tenants/{id}/locale/default',
            normalizationContext: ['groups' => ['tenant:read']],
            denormalizationContext: ['groups' => ['tenant:locale:set']],
            provider: TenantItemProvider::class,
            processor: SetDefaultLocaleProcessor::class
        ),
        new Post(
            uriTemplate: '/tenants/{id}/locale/enable',
            normalizationContext: ['groups' => ['tenant:read']],
            denormalizationContext: ['groups' => ['tenant:locale:enable']],
            provider: TenantItemProvider::class,
            processor: EnableLocaleProcessor::class
        ),
        new Delete(
            uriTemplate: '/tenants/{id}/locale/{localeCode}',
            normalizationContext: ['groups' => ['tenant:read']],
            provider: TenantItemProvider::class,
            processor: DisableLocaleProcessor::class
        ),
    ]
)]
final class TenantResource
{
    #[Groups(['tenant:read'])]
    public ?string $id = null;

    #[Groups(['tenant:read', 'tenant:create', 'tenant:update'])]
    #[Assert\NotBlank(groups: ['tenant:create', 'tenant:update'])]
    #[Assert\Length(min: 3, max: 100, groups: ['tenant:create', 'tenant:update'])]
    public ?string $name = null;

    #[Groups(['tenant:read', 'tenant:create', 'tenant:update'])]
    #[Assert\NotBlank(groups: ['tenant:create', 'tenant:update'])]
    #[Assert\Email(groups: ['tenant:create', 'tenant:update'])]
    public ?string $ownerEmail = null;

    #[Groups(['tenant:read'])]
    public ?string $status = null;

    #[Groups(['tenant:read'])]
    public ?string $createdAt = null;

    #[Groups(['tenant:read', 'tenant:locale:set'])]
    #[Assert\NotBlank(groups: ['tenant:locale:set'])]
    #[Assert\Length(min: 2, max: 2, groups: ['tenant:locale:set'])]
    public ?string $defaultLocale = null;

    #[Groups(['tenant:read'])]
    public ?array $enabledLocales = null;

    #[Groups(['tenant:locale:enable'])]
    #[Assert\NotBlank(groups: ['tenant:locale:enable'])]
    #[Assert\Length(min: 2, max: 2, groups: ['tenant:locale:enable'])]
    public ?string $localeCode = null;
}
