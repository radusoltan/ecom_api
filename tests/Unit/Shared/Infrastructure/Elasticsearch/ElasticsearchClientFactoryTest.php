<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Elasticsearch;

use App\Shared\Infrastructure\Elasticsearch\ElasticsearchClientFactory;
use Elastic\Elasticsearch\Client;
use PHPUnit\Framework\TestCase;

final class ElasticsearchClientFactoryTest extends TestCase
{
    public function testCreateReturnsClient(): void
    {
        $client = ElasticsearchClientFactory::create(
            'localhost:9200',
            'elastic',
            'WsAEcDWAbQjb5XGUnpvk'
        );

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testClientCanConnectToElasticsearch(): void
    {
        $client = ElasticsearchClientFactory::create(
            'localhost:9200',
            'elastic',
            'WsAEcDWAbQjb5XGUnpvk'
        );

        $response = $client->info();

        $this->assertIsArray($response->asArray());
        $this->assertArrayHasKey('version', $response->asArray());
        $this->assertArrayHasKey('cluster_name', $response->asArray());
    }
}
