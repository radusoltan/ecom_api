<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Internationalization\Infrastructure\ApiPlatform\State\TranslationManagementProcessor;
use App\Internationalization\Infrastructure\ApiPlatform\State\TranslationManagementProvider;

/**
 * Translation Management API Resource.
 *
 * Provides CRUD operations for managing translations.
 *
 * Endpoints:
 * - GET /api/translation-management - List translations with filters
 * - GET /api/translation-management/{id} - Get single translation
 * - POST /api/translation-management - Create new translation
 * - PUT /api/translation-management/{id} - Update translation
 * - DELETE /api/translation-management/{id} - Delete translation
 */
#[ApiResource(
    shortName: 'TranslationManagement',
    operations: [
        new GetCollection(
            uriTemplate: '/translation-management',
            security: "is_granted('ROLE_USER')",
            provider: TranslationManagementProvider::class,
        ),
        new Get(
            uriTemplate: '/translation-management/{id}',
            security: "is_granted('ROLE_USER')",
            provider: TranslationManagementProvider::class,
        ),
        new Post(
            uriTemplate: '/translation-management',
            security: "is_granted('ROLE_ADMIN')",
            processor: TranslationManagementProcessor::class,
        ),
        new Put(
            uriTemplate: '/translation-management/{id}',
            security: "is_granted('ROLE_ADMIN')",
            processor: TranslationManagementProcessor::class,
        ),
        new Delete(
            uriTemplate: '/translation-management/{id}',
            security: "is_granted('ROLE_ADMIN')",
            processor: TranslationManagementProcessor::class,
        ),
    ],
    paginationEnabled: true,
    paginationItemsPerPage: 50,
)]
final class TranslationManagementResource
{
    public function __construct(
        public ?int $id = null,
        public ?string $tenantId = null,
        public ?string $locale = null,
        public ?string $domain = null,
        public ?string $key = null,
        public ?string $value = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
