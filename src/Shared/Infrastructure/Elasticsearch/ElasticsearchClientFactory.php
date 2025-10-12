<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

final class ElasticsearchClientFactory
{
    public static function create(
        string $host,
        string $user,
        string $password
    ): Client {
        return ClientBuilder::create()
            ->setHosts([$host])
            ->setBasicAuthentication($user, $password)
            ->setRetries(2)
            ->build();
    }
}
