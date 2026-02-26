<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\MediaType as OpenApiMediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use ApiPlatform\OpenApi\Model\RequestBody as OpenApiRequestBody;
use App\Catalog\Infrastructure\ApiPlatform\State\GetProductTranslationsProvider;
use App\Catalog\Infrastructure\ApiPlatform\State\UpdateProductTranslationsProcessor;

/**
 * API resource for product translations
 * Provides endpoints for managing translations of product content.
 */
#[ApiResource(
    shortName: 'ProductTranslation',
    security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')",
    routePrefix: '/products/{id}',
    operations: [
        new Get(
            uriTemplate: '/translations',
            provider: GetProductTranslationsProvider::class,
            openapi: new OpenApiOperation(
                summary: 'Get product translations',
                description: 'Retrieve all translations for a product',
                parameters: [
                    new OpenApiParameter(
                        name: 'id',
                        in: 'path',
                        description: 'Product ID',
                        required: true,
                        schema: ['type' => 'string'],
                    ),
                ],
            ),
        ),
        new Patch(
            uriTemplate: '/translations',
            processor: UpdateProductTranslationsProcessor::class,
            openapi: new OpenApiOperation(
                summary: 'Update product translations',
                description: 'Update translations for a specific locale',
                parameters: [
                    new OpenApiParameter(
                        name: 'id',
                        in: 'path',
                        description: 'Product ID',
                        required: true,
                        schema: ['type' => 'string'],
                    ),
                ],
                requestBody: new OpenApiRequestBody(
                    description: 'Payload describing the updated translations.',
                    content: new \ArrayObject([
                        'application/json' => new OpenApiMediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['locale'],
                                'properties' => [
                                    'locale' => [
                                        'type' => 'string',
                                        'description' => 'Locale code (e.g., fr_FR, de_DE)',
                                    ],
                                    'name' => [
                                        'type' => 'string',
                                        'description' => 'Translated product name',
                                    ],
                                    'description' => [
                                        'type' => 'string',
                                        'description' => 'Translated product description',
                                    ],
                                    'shortDescription' => [
                                        'type' => 'string',
                                        'description' => 'Translated short description',
                                    ],
                                ],
                            ]),
                        ),
                    ]),
                    required: true,
                ),
            ),
        ),
    ],
)]
class ProductTranslationResource
{
    public string $productId;
    /** @var array<string, string> */
    public array $translations = [];
}
