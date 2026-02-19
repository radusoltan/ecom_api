<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Elasticsearch;

use App\Shared\Infrastructure\Elasticsearch\ElasticsearchClientFactory;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use PHPUnit\Framework\TestCase;

final class ElasticsearchClientFactoryTest extends TestCase
{
    public function testCreateReturnsClient(): void
    {
        $client = ElasticsearchClientFactory::create(
            $this->getElasticsearchHost(),
            $this->getElasticsearchUser(),
            $this->getElasticsearchPassword(),
            $this->shouldVerifySsl()
        );

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testClientCanConnectToElasticsearch(): void
    {
        $client = ElasticsearchClientFactory::create(
            $this->getElasticsearchHost(),
            $this->getElasticsearchUser(),
            $this->getElasticsearchPassword(),
            $this->shouldVerifySsl()
        );

        try {
            $response = $client->info();
        } catch (NoNodeAvailableException|ClientResponseException|ServerResponseException $exception) {
            self::markTestSkipped(sprintf(
                'Elasticsearch not reachable at %s: %s',
                $this->getElasticsearchHost(),
                $exception->getMessage()
            ));

            return;
        }

        $this->assertIsArray($response->asArray());
        $this->assertArrayHasKey('version', $response->asArray());
        $this->assertArrayHasKey('cluster_name', $response->asArray());
    }

    private function getElasticsearchHost(): string
    {
        $host = getenv('ELASTICSEARCH_HOST');

        return false !== $host ? $host : 'https://localhost:9200';
    }

    private function getElasticsearchUser(): string
    {
        $user = getenv('ELASTICSEARCH_USER');

        return false !== $user ? $user : 'elastic';
    }

    private function getElasticsearchPassword(): string
    {
        $password = getenv('ELASTICSEARCH_PASSWORD');

        return false !== $password ? $password : 'WsAEcDWAbQjb5XGUnpvk';
    }

    private function shouldVerifySsl(): bool
    {
        $value = getenv('ELASTICSEARCH_VERIFY_SSL');
        if (false === $value) {
            return true;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? true;
    }
}
