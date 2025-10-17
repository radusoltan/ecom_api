<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Elasticsearch;

use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;

final readonly class IndexManager
{
    public function __construct(
        private Client $client,
        private SynonymManager $synonymManager
    ) {}

    public function createProductIndex(TenantId $tenantId, Locale $locale): void
    {
        $indexName = $this->getProductIndexName($tenantId, $locale);

        if ($this->indexExists($indexName)) {
            return;
        }

        $params = [
            'index' => $indexName,
            'body' => [
                'settings' => $this->getProductIndexSettings($locale),
                'mappings' => $this->getProductIndexMappings(),
            ],
        ];

        $this->client->indices()->create($params);
    }

    public function createCategoryIndex(TenantId $tenantId, Locale $locale): void
    {
        $indexName = $this->getCategoryIndexName($tenantId, $locale);

        if ($this->indexExists($indexName)) {
            return;
        }

        $params = [
            'index' => $indexName,
            'body' => [
                'settings' => $this->getCategoryIndexSettings($locale),
                'mappings' => $this->getCategoryIndexMappings(),
            ],
        ];

        $this->client->indices()->create($params);
    }

    public function deleteIndex(string $indexName): void
    {
        if (!$this->indexExists($indexName)) {
            return;
        }

        $this->client->indices()->delete(['index' => $indexName]);
    }

    public function indexExists(string $indexName): bool
    {
        try {
            return $this->client->indices()->exists(['index' => $indexName])->asBool();
        } catch (ClientResponseException | ServerResponseException) {
            return false;
        }
    }

    public function refresh(string $indexName): void
    {
        if (!$this->indexExists($indexName)) {
            return;
        }

        $this->client->indices()->refresh(['index' => $indexName]);
    }

    public function getProductIndexName(TenantId $tenantId, Locale $locale): string
    {
        $localeString = str_replace('_', '', strtolower($locale->toString()));
        return sprintf('%s_products_%s', $tenantId->toString(), $localeString);
    }

    public function getCategoryIndexName(TenantId $tenantId, Locale $locale): string
    {
        $localeString = str_replace('_', '', strtolower($locale->toString()));
        return sprintf('%s_categories_%s', $tenantId->toString(), $localeString);
    }

    /**
     * @return array<string, mixed>
     */
    private function getProductIndexSettings(Locale $locale): array
    {
        $analyzer = $this->getAnalyzerForLocale($locale);
        $synonyms = $this->synonymManager->getSynonymsForLocale($locale);

        return [
            'number_of_shards' => 1,
            'number_of_replicas' => 0,
            'analysis' => [
                'analyzer' => [
                    'product_analyzer' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => [
                            'lowercase',
                            'asciifolding',
                            'product_synonym_filter',
                            $analyzer . '_stemmer',
                            'unique',
                        ],
                    ],
                    'product_search_analyzer' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => [
                            'lowercase',
                            'asciifolding',
                            'product_synonym_filter',
                            $analyzer . '_stemmer',
                            'unique',
                        ],
                    ],
                ],
                'filter' => [
                    $analyzer . '_stemmer' => [
                        'type' => 'stemmer',
                        'language' => $analyzer,
                    ],
                    'product_synonym_filter' => [
                        'type' => 'synonym',
                        'synonyms' => $synonyms,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getCategoryIndexSettings(Locale $locale): array
    {
        $analyzer = $this->getAnalyzerForLocale($locale);
        $synonyms = $this->synonymManager->getSynonymsForLocale($locale);

        return [
            'number_of_shards' => 1,
            'number_of_replicas' => 0,
            'analysis' => [
                'analyzer' => [
                    'category_analyzer' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => [
                            'lowercase',
                            'asciifolding',
                            'category_synonym_filter',
                            $analyzer . '_stemmer',
                            'unique',
                        ],
                    ],
                ],
                'filter' => [
                    $analyzer . '_stemmer' => [
                        'type' => 'stemmer',
                        'language' => $analyzer,
                    ],
                    'category_synonym_filter' => [
                        'type' => 'synonym',
                        'synonyms' => $synonyms,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getProductIndexMappings(): array
    {
        return [
            'properties' => [
                'id' => ['type' => 'keyword'],
                'tenant_id' => ['type' => 'keyword'],
                'sku' => ['type' => 'keyword'],
                'name' => [
                    'type' => 'text',
                    'analyzer' => 'product_analyzer',
                    'fields' => [
                        'keyword' => ['type' => 'keyword'],
                        'suggest' => [
                            'type' => 'completion',
                        ],
                    ],
                ],
                'description' => [
                    'type' => 'text',
                    'analyzer' => 'product_analyzer',
                ],
                'slug' => ['type' => 'keyword'],
                'price' => [
                    'type' => 'scaled_float',
                    'scaling_factor' => 100,
                ],
                'currency' => ['type' => 'keyword'],
                'status' => ['type' => 'keyword'],
                'is_featured' => ['type' => 'boolean'],
                'category_ids' => ['type' => 'keyword'],
                'category_names' => ['type' => 'keyword'],
                'image_url' => ['type' => 'keyword', 'index' => false],
                'locale' => ['type' => 'keyword'],
                'created_at' => ['type' => 'date'],
                'updated_at' => ['type' => 'date'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getCategoryIndexMappings(): array
    {
        return [
            'properties' => [
                'id' => ['type' => 'keyword'],
                'tenant_id' => ['type' => 'keyword'],
                'name' => [
                    'type' => 'text',
                    'analyzer' => 'category_analyzer',
                    'fields' => [
                        'keyword' => ['type' => 'keyword'],
                    ],
                ],
                'description' => [
                    'type' => 'text',
                    'analyzer' => 'category_analyzer',
                ],
                'slug' => ['type' => 'keyword'],
                'parent_id' => ['type' => 'keyword'],
                'position' => ['type' => 'integer'],
                'is_active' => ['type' => 'boolean'],
                'show_on_front' => ['type' => 'boolean'],
                'locale' => ['type' => 'keyword'],
                'created_at' => ['type' => 'date'],
                'updated_at' => ['type' => 'date'],
            ],
        ];
    }

    private function getAnalyzerForLocale(Locale $locale): string
    {
        return match ($locale->getLanguage()) {
            'en' => 'english',
            'ro' => 'romanian',
            'de' => 'german',
            'fr' => 'french',
            'es' => 'spanish',
            'it' => 'italian',
            default => 'english',
        };
    }
}
