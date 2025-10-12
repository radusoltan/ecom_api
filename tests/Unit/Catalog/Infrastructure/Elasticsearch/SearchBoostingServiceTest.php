<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Elasticsearch;

use App\Catalog\Infrastructure\Elasticsearch\SearchBoostingService;
use PHPUnit\Framework\TestCase;

final class SearchBoostingServiceTest extends TestCase
{
    private SearchBoostingService $boostingService;

    protected function setUp(): void
    {
        $this->boostingService = new SearchBoostingService();
    }

    public function testApplyFunctionScoresWithoutSearchTerm(): void
    {
        $baseQuery = ['bool' => ['must' => [['term' => ['status' => 'active']]]]];

        $result = $this->boostingService->applyFunctionScores($baseQuery, null);

        $this->assertArrayHasKey('function_score', $result);
        $this->assertArrayHasKey('query', $result['function_score']);
        $this->assertArrayHasKey('functions', $result['function_score']);
        $this->assertEquals($baseQuery, $result['function_score']['query']);

        // Should have recency, active status, and price boost functions (no exact match without term)
        $functions = $result['function_score']['functions'];
        $this->assertGreaterThanOrEqual(3, count($functions));
    }

    public function testApplyFunctionScoresWithSearchTerm(): void
    {
        $baseQuery = ['bool' => ['must' => [['term' => ['status' => 'active']]]]];
        $searchTerm = 'laptop';

        $result = $this->boostingService->applyFunctionScores($baseQuery, $searchTerm);

        $this->assertArrayHasKey('function_score', $result);
        $functions = $result['function_score']['functions'];

        // Should have all boost functions including exact match and SKU boost
        $this->assertGreaterThanOrEqual(5, count($functions));

        // Verify exact match boost exists
        $hasExactMatchBoost = false;
        foreach ($functions as $function) {
            if (isset($function['filter']['term']['name.keyword'])) {
                $this->assertEquals($searchTerm, $function['filter']['term']['name.keyword']);
                $this->assertEquals(5.0, $function['weight']);
                $hasExactMatchBoost = true;
                break;
            }
        }
        $this->assertTrue($hasExactMatchBoost, 'Should have exact match boost');
    }

    public function testFunctionScoreConfiguration(): void
    {
        $baseQuery = ['bool' => ['must' => []]];
        $result = $this->boostingService->applyFunctionScores($baseQuery, 'test');

        $functionScore = $result['function_score'];

        $this->assertEquals('sum', $functionScore['score_mode']);
        $this->assertEquals('multiply', $functionScore['boost_mode']);
        $this->assertEquals(20, $functionScore['max_boost']);
        $this->assertEquals(0.01, $functionScore['min_score']);
    }

    public function testRecencyBoostIsConfigured(): void
    {
        $baseQuery = ['bool' => ['must' => []]];
        $result = $this->boostingService->applyFunctionScores($baseQuery, null);

        $functions = $result['function_score']['functions'];

        // Find recency boost function
        $hasRecencyBoost = false;
        foreach ($functions as $function) {
            if (isset($function['gauss']['created_at'])) {
                $gaussConfig = $function['gauss']['created_at'];
                $this->assertEquals('now', $gaussConfig['origin']);
                $this->assertEquals('30d', $gaussConfig['scale']);
                $this->assertEquals('7d', $gaussConfig['offset']);
                $this->assertEquals(0.5, $gaussConfig['decay']);
                $this->assertEquals(1.5, $function['weight']);
                $hasRecencyBoost = true;
                break;
            }
        }

        $this->assertTrue($hasRecencyBoost, 'Should have recency boost configuration');
    }

    public function testActiveStatusBoostIsConfigured(): void
    {
        $baseQuery = ['bool' => ['must' => []]];
        $result = $this->boostingService->applyFunctionScores($baseQuery, null);

        $functions = $result['function_score']['functions'];

        // Find active status boost
        $hasActiveBoost = false;
        foreach ($functions as $function) {
            if (isset($function['filter']['term']['status']) && $function['filter']['term']['status'] === 'active') {
                $this->assertEquals(2.0, $function['weight']);
                $hasActiveBoost = true;
                break;
            }
        }

        $this->assertTrue($hasActiveBoost, 'Should have active status boost');
    }

    public function testSKUExactMatchBoostIsConfigured(): void
    {
        $baseQuery = ['bool' => ['must' => []]];
        $searchTerm = 'prd-123456';

        $result = $this->boostingService->applyFunctionScores($baseQuery, $searchTerm);
        $functions = $result['function_score']['functions'];

        // Find SKU exact match boost
        $hasSKUBoost = false;
        foreach ($functions as $function) {
            if (isset($function['filter']['term']['sku'])) {
                $this->assertEquals(strtoupper($searchTerm), $function['filter']['term']['sku']);
                $this->assertEquals(10.0, $function['weight']);
                $hasSKUBoost = true;
                break;
            }
        }

        $this->assertTrue($hasSKUBoost, 'Should have SKU exact match boost');
    }

    public function testGetFieldBoosts(): void
    {
        $boosts = $this->boostingService->getFieldBoosts();

        $this->assertIsArray($boosts);
        $this->assertArrayHasKey('name', $boosts);
        $this->assertArrayHasKey('sku', $boosts);
        $this->assertArrayHasKey('description', $boosts);
        $this->assertArrayHasKey('category_names', $boosts);

        // Name should be most important
        $this->assertGreaterThan($boosts['description'], $boosts['name']);
        $this->assertGreaterThan($boosts['description'], $boosts['sku']);
        $this->assertGreaterThan($boosts['description'], $boosts['category_names']);
    }

    public function testGetFuzzyConfig(): void
    {
        $config = $this->boostingService->getFuzzyConfig();

        $this->assertIsArray($config);
        $this->assertEquals('AUTO', $config['fuzziness']);
        $this->assertEquals(2, $config['prefix_length']);
        $this->assertEquals(50, $config['max_expansions']);
        $this->assertTrue($config['fuzzy_transpositions']);
    }

    public function testEmptySearchTermIsHandledGracefully(): void
    {
        $baseQuery = ['bool' => ['must' => []]];

        // Empty string should be treated like null
        $result = $this->boostingService->applyFunctionScores($baseQuery, '');

        $functions = $result['function_score']['functions'];

        // Should not have exact match or SKU boost for empty string
        $hasNameExactMatch = false;
        $hasSKUExactMatch = false;

        foreach ($functions as $function) {
            if (isset($function['filter']['term']['name.keyword'])) {
                $hasNameExactMatch = true;
            }
            if (isset($function['filter']['term']['sku'])) {
                $hasSKUExactMatch = true;
            }
        }

        $this->assertFalse($hasNameExactMatch, 'Should not have name exact match for empty string');
        $this->assertFalse($hasSKUExactMatch, 'Should not have SKU exact match for empty string');
    }

    public function testWhitespaceOnlySearchTermIsHandledGracefully(): void
    {
        $baseQuery = ['bool' => ['must' => []]];

        $result = $this->boostingService->applyFunctionScores($baseQuery, '   ');

        $functions = $result['function_score']['functions'];

        // Should not have exact match or SKU boost for whitespace
        $hasExactMatchFilter = false;
        foreach ($functions as $function) {
            if (isset($function['filter']['term']['name.keyword']) || isset($function['filter']['term']['sku'])) {
                $hasExactMatchFilter = true;
            }
        }

        $this->assertFalse($hasExactMatchFilter, 'Should not have exact match filters for whitespace');
    }
}
