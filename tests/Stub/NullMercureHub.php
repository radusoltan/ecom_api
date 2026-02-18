<?php

declare(strict_types=1);

namespace App\Tests\Stub;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Update;

/**
 * No-op Mercure hub used in the test environment to avoid network calls.
 */
final class NullMercureHub implements HubInterface
{
    /**
     * @var list<Update>
     */
    private array $updates = [];

    private TokenProviderInterface $provider;
    private ?TokenFactoryInterface $factory;
    private string $url;

    public function __construct(
        ?TokenProviderInterface $provider = null,
        ?TokenFactoryInterface $factory = null,
        ?string $url = null,
    ) {
        $this->provider = $provider ?? new StaticTokenProvider('test-mercure-token');
        $this->factory = $factory;
        $this->url = $url ?? 'http://127.0.0.1/.well-known/mercure';
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPublicUrl(): string
    {
        return $this->url;
    }

    public function getProvider(): TokenProviderInterface
    {
        return $this->provider;
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return $this->factory;
    }

    public function publish(Update $update): string
    {
        $this->updates[] = $update;

        return 'null-hub-update';
    }

    /**
     * @return list<Update>
     */
    public function getPublishedUpdates(): array
    {
        return $this->updates;
    }
}
