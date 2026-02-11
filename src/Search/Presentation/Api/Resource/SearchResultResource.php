<?php

declare(strict_types=1);

namespace App\Search\Presentation\Api\Resource;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Search\Infrastructure\ApiPlatform\State\SearchProvider;

/**
 * SearchResultResource
 *
 * API Platform resource for product search.
 *
 * GET /api/search?q=laptop&page=1&per_page=24&sort_by=price&sort_order=asc
 * GET /api/search/autocomplete?q=lap&limit=5
 */
#[GetCollection(
    uriTemplate: '/search',
    provider: SearchProvider::class,
    openapiContext: [
        'summary' => 'Search for products',
        'description' => 'Full-text search with filters, sorting, and pagination',
        'parameters' => [
            [
                'name' => 'q',
                'in' => 'query',
                'required' => true,
                'schema' => ['type' => 'string', 'minLength' => 1],
                'description' => 'Search query',
            ],
            [
                'name' => 'page',
                'in' => 'query',
                'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'description' => 'Page number',
            ],
            [
                'name' => 'per_page',
                'in' => 'query',
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 24],
                'description' => 'Items per page',
            ],
            [
                'name' => 'sort_by',
                'in' => 'query',
                'schema' => ['type' => 'string', 'enum' => ['relevance', 'price', 'name', 'created_at'], 'default' => 'relevance'],
                'description' => 'Sort field',
            ],
            [
                'name' => 'sort_order',
                'in' => 'query',
                'schema' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'],
                'description' => 'Sort order',
            ],
            [
                'name' => 'filter[category]',
                'in' => 'query',
                'schema' => ['type' => 'string'],
                'description' => 'Category ID filter',
            ],
            [
                'name' => 'filter[price_min]',
                'in' => 'query',
                'schema' => ['type' => 'number'],
                'description' => 'Minimum price filter',
            ],
            [
                'name' => 'filter[price_max]',
                'in' => 'query',
                'schema' => ['type' => 'number'],
                'description' => 'Maximum price filter',
            ],
        ],
    ]
)]
#[Get(
    uriTemplate: '/search/autocomplete',
    provider: SearchProvider::class,
    openapiContext: [
        'summary' => 'Autocomplete product suggestions',
        'description' => 'Get top N product suggestions for autocomplete',
        'parameters' => [
            [
                'name' => 'q',
                'in' => 'query',
                'required' => true,
                'schema' => ['type' => 'string', 'minLength' => 2],
                'description' => 'Search query (minimum 2 characters)',
            ],
            [
                'name' => 'limit',
                'in' => 'query',
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 5],
                'description' => 'Maximum number of suggestions',
            ],
        ],
    ]
)]
final class SearchResultResource
{
    public function __construct(
        public array $hits = [],
        public int $total = 0,
        public int $page = 1,
        public int $perPage = 24,
        public int $totalPages = 0,
        public array $facets = [],
        public float $took = 0.0,
    ) {}
}
