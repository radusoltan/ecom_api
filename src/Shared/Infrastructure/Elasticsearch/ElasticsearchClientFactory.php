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
        string $password,
        bool $verifySsl = true
    ): Client {
        $builder = ClientBuilder::create()
            ->setHosts([rtrim($host, '/')])
            ->setRetries(2);

        if ('' !== $user || '' !== $password) {
            $builder->setBasicAuthentication($user, $password);
        }

        $builder->setSSLVerification($verifySsl);

        return $builder->build();
    }
}
