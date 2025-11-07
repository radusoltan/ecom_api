<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Elasticsearch;

/**
 * Service for managing search relevance boosting.
 *
 * Implements advanced boosting strategies:
 * - Recency boost (newer products rank higher)
 * - Popularity boost (based on views/sales)
 * - Field-level boosting (name > SKU > description)
 * - Exact match boosting
 *
 * Business Rules (PRD Section 10.1):
 * - Active products boosted over inactive
 * - Recent products (< 30 days) get boost
 * - Name field 3x more important than description
 * - SKU exact matches get significant boost
 */
final readonly class SearchBoostingService
{
    /**
     * Build function score query for advanced relevance tuning.
     *
     * @param array<string, mixed> $baseQuery  Base bool query
     * @param string|null          $searchTerm Original search term for exact matching
     *
     * @return array<string, mixed> Enhanced query with function scores
     */
    public function applyFunctionScores(array $baseQuery, ?string $searchTerm = null): array
    {
        $functions = [];

        // 1. Recency boost - newer products get higher scores
        $functions[] = [
            'gauss' => [
                'created_at' => [
                    'origin' => 'now',
                    'scale' => '30d',      // Products < 30 days get full boost
                    'offset' => '7d',       // Grace period
                    'decay' => 0.5,         // Decay rate
                ],
            ],
            'weight' => 1.5,
        ];

        // 2. Exact match boost - exact matches on name get highest relevance
        if (null !== $searchTerm && '' !== trim($searchTerm)) {
            $functions[] = [
                'filter' => [
                    'term' => [
                        'name.keyword' => $searchTerm,
                    ],
                ],
                'weight' => 5.0,
            ];

            // 3. SKU exact match - if searching by SKU, boost significantly
            $functions[] = [
                'filter' => [
                    'term' => [
                        'sku' => strtoupper($searchTerm),
                    ],
                ],
                'weight' => 10.0,
            ];
        }

        // 4. Active status boost - prefer active products
        $functions[] = [
            'filter' => [
                'term' => [
                    'status' => 'active',
                ],
            ],
            'weight' => 2.0,
        ];

        // 5. Price range boost - favor mid-range products (not too cheap, not too expensive)
        // This helps surface quality products
        $functions[] = [
            'gauss' => [
                'price' => [
                    'origin' => 100,    // Sweet spot price
                    'scale' => 50,       // Range around sweet spot
                    'decay' => 0.7,
                ],
            ],
            'weight' => 1.2,
        ];

        return [
            'function_score' => [
                'query' => $baseQuery,
                'functions' => $functions,
                'score_mode' => 'sum',      // Add all function scores
                'boost_mode' => 'multiply',  // Multiply with base query score
                'max_boost' => 20,           // Cap maximum boost
                'min_score' => 0.01,         // Filter out very low scores
            ],
        ];
    }

    /**
     * Get field-level boost configuration.
     *
     * @return array<string, float> Field name => boost factor
     */
    public function getFieldBoosts(): array
    {
        return [
            'name' => 3.0,           // Product name most important
            'sku' => 2.0,            // SKU second most important
            'description' => 1.0,     // Description baseline
            'category_names' => 1.5,  // Categories moderately important
        ];
    }

    /**
     * Get phrase boost configuration for better multi-word matching.
     *
     * @return array<string, mixed>
     */
    public function getPhraseBoostConfig(): array
    {
        return [
            'type' => 'phrase',
            'slop' => 2,          // Allow 2 words between phrase terms
            'boost' => 3.0,        // Phrase matches get 3x boost
        ];
    }

    /**
     * Get fuzzy matching configuration.
     *
     * @return array<string, mixed>
     */
    public function getFuzzyConfig(): array
    {
        return [
            'fuzziness' => 'AUTO',              // AUTO adjusts based on term length
            'prefix_length' => 2,                // First 2 chars must match exactly
            'max_expansions' => 50,              // Limit fuzzy expansions for performance
            'fuzzy_transpositions' => true,      // Allow character swaps (teh -> the)
        ];
    }
}
