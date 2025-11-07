<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\AutocompleteProducts;

use App\Shared\Infrastructure\Elasticsearch\IndexManager;
use Elastic\Elasticsearch\Client;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AutocompleteProductsQueryHandler
{
    public function __construct(
        private Client $client,
        private IndexManager $indexManager
    ) {
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function __invoke(AutocompleteProductsQuery $query): array
    {
        $indexName = $this->indexManager->getProductIndexName($query->tenantId, $query->locale);

        // Check if index exists
        if (!$this->indexManager->indexExists($indexName)) {
            return [];
        }

        $searchParams = [
            'index' => $indexName,
            'body' => [
                'suggest' => [
                    'product-suggest' => [
                        'prefix' => $query->query,
                        'completion' => [
                            'field' => 'name.suggest',
                            'size' => $query->limit,
                            'skip_duplicates' => true,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->client->search($searchParams);
        $result = $response->asArray();

        return $this->parseSuggestions($result);
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<array<string, mixed>>
     */
    private function parseSuggestions(array $response): array
    {
        $suggestions = [];

        if (isset($response['suggest']['product-suggest'][0]['options'])) {
            foreach ($response['suggest']['product-suggest'][0]['options'] as $option) {
                $suggestions[] = [
                    'text' => $option['text'],
                    'score' => $option['_score'] ?? 0.0,
                ];
            }
        }

        return $suggestions;
    }
}
