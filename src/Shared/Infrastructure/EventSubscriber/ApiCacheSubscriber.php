<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * API Cache Headers Subscriber.
 *
 * Adds HTTP cache headers to API responses for improved performance:
 * - Cache-Control headers
 * - ETags for validation
 * - Vary headers for content negotiation
 *
 * Business Rules (PRD Section 9.1):
 * - Cache public GET requests only
 * - Set appropriate max-age based on resource type
 * - Support tenant and locale variations
 * - Enable conditional requests with ETags
 */
final class ApiCacheSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10], // Run after main processing
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // Only process main requests
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only for API routes
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        // Only for successful GET requests
        if ('GET' !== $request->getMethod() || !$response->isSuccessful()) {
            return;
        }

        $this->mergeVaryHeaders($response, ['Accept', 'Accept-Language', 'X-Tenant-ID']);

        // Skip if cache explicitly disabled
        if ('true' === $request->headers->get('X-No-Cache')) {
            $response->headers->set('Cache-Control', 'private, no-cache');

            return;
        }

        $policy = $this->cachePolicy($request->getPathInfo());
        $this->applyCachePolicy($response, $policy);
        $this->applyEtag($request, $response);

        $response->headers->set('X-Cache-TTL', (string) $policy['maxAge']);
    }

    /**
     * @return array{scope: string, maxAge: int, staleWhileRevalidate: int|null}
     */
    private function cachePolicy(string $path): array
    {
        return match (true) {
            str_contains($path, '/export') => [
                'scope' => 'no-store',
                'maxAge' => 0,
                'staleWhileRevalidate' => null,
            ],
            str_contains($path, '/stock') => [
                'scope' => 'private-no-store',
                'maxAge' => 0,
                'staleWhileRevalidate' => null,
            ],
            str_contains($path, '/orders'),
            str_contains($path, '/payments'),
            str_contains($path, '/customers'),
            str_contains($path, '/returns'),
            str_contains($path, '/cart') => [
                'scope' => 'private-no-cache',
                'maxAge' => 0,
                'staleWhileRevalidate' => null,
            ],
            str_contains($path, '/invoices') && str_contains($path, '/pdf') => [
                'scope' => 'private-cache',
                'maxAge' => 3600,
                'staleWhileRevalidate' => null,
            ],
            str_contains($path, '/translations') => [
                'scope' => 'public',
                'maxAge' => 3600,
                'staleWhileRevalidate' => 7200,
            ],
            str_contains($path, '/search'),
            str_contains($path, '/autocomplete') => [
                'scope' => 'public',
                'maxAge' => 60,
                'staleWhileRevalidate' => 120,
            ],
            str_contains($path, '/categories'),
            str_contains($path, '/home-categories') => [
                'scope' => 'public',
                'maxAge' => 300,
                'staleWhileRevalidate' => 600,
            ],
            str_contains($path, '/warehouses') => [
                'scope' => 'public',
                'maxAge' => 600,
                'staleWhileRevalidate' => 1200,
            ],
            str_contains($path, '/tenants') => [
                'scope' => 'public',
                'maxAge' => 600,
                'staleWhileRevalidate' => null,
            ],
            str_contains($path, '/price-lists'),
            str_contains($path, '/price_lists'),
            str_contains($path, '/promotions'),
            str_contains($path, '/products'),
            str_contains($path, '/featured-products') => [
                'scope' => 'public',
                'maxAge' => 300,
                'staleWhileRevalidate' => 600,
            ],
            default => [
                'scope' => 'public',
                'maxAge' => 300,
                'staleWhileRevalidate' => 600,
            ],
        };
    }

    /**
     * @param array{scope: string, maxAge: int, staleWhileRevalidate: int|null} $policy
     */
    private function applyCachePolicy(Response $response, array $policy): void
    {
        if ('public' === $policy['scope']) {
            $response->setPublic();
            $response->setMaxAge($policy['maxAge']);
            $response->setSharedMaxAge($policy['maxAge']);

            if (null !== $policy['staleWhileRevalidate']) {
                $response->headers->addCacheControlDirective('stale-while-revalidate', (string) $policy['staleWhileRevalidate']);
            }

            return;
        }

        if ('private-cache' === $policy['scope']) {
            $response->setPrivate();
            $response->setMaxAge($policy['maxAge']);
            $response->headers->set('Cache-Control', sprintf('private, max-age=%d', $policy['maxAge']));

            return;
        }

        if ('private-no-store' === $policy['scope']) {
            $response->headers->set('Cache-Control', 'private, no-store');

            return;
        }

        if ('no-store' === $policy['scope']) {
            $response->headers->set('Cache-Control', 'no-cache, no-store');

            return;
        }

        $response->headers->set('Cache-Control', 'private, no-cache');
    }

    private function applyEtag(Request $request, Response $response): void
    {
        $etagSeed = $this->etagSeed($request, $response);

        if (null === $etagSeed) {
            return;
        }

        $response->setEtag(md5($etagSeed));

        $lastModified = $this->latestUpdatedAt($request->attributes->get('data'));
        if (null !== $lastModified) {
            $response->setLastModified($lastModified);
        }

        $response->isNotModified($request);
    }

    /**
     * @param array<string> $headers
     */
    private function mergeVaryHeaders(Response $response, array $headers): void
    {
        $response->headers->set('Vary', implode(', ', array_values(array_unique([
            ...$response->getVary(),
            ...$headers,
        ]))));
    }

    private function etagSeed(Request $request, Response $response): ?string
    {
        $data = $request->attributes->get('data');
        $tokens = $this->versionTokens($data);

        if ([] !== $tokens) {
            sort($tokens);

            return implode('|', $tokens);
        }

        $content = $response->getContent();
        if (false === $content || '' === $content) {
            return null;
        }

        return $content;
    }

    /**
     * @return array<int, string>
     */
    private function versionTokens(mixed $data): array
    {
        if ($data instanceof \Traversable) {
            $data = iterator_to_array($data, false);
        }

        if (is_array($data)) {
            if ($this->isVersionedArrayPayload($data)) {
                $token = $this->arrayToken($data);

                return null === $token ? [] : [$token];
            }

            $tokens = [];
            foreach ($data as $item) {
                array_push($tokens, ...$this->versionTokens($item));
            }

            return $tokens;
        }

        if (!is_object($data)) {
            return [];
        }

        $updatedAt = $this->objectUpdatedAt($data);
        if (null === $updatedAt) {
            return [];
        }

        $identifier = $this->objectIdentifier($data) ?? spl_object_hash($data);

        return [$this->buildVersionToken($identifier, $updatedAt->format(\DateTimeInterface::ATOM))];
    }

    private function latestUpdatedAt(mixed $data): ?\DateTimeImmutable
    {
        $tokens = $this->versionTokens($data);
        $latest = null;

        foreach ($tokens as $token) {
            [, $updatedAt] = array_pad(explode('|', $token, 2), 2, null);
            if (null === $updatedAt) {
                continue;
            }

            try {
                $candidate = new \DateTimeImmutable($updatedAt);
            } catch (\Exception) {
                continue;
            }

            if (null === $latest || $candidate > $latest) {
                $latest = $candidate;
            }
        }

        return $latest;
    }

    /**
     * @param array<mixed> $data
     */
    private function isVersionedArrayPayload(array $data): bool
    {
        return array_key_exists('updatedAt', $data) || array_key_exists('updated_at', $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function arrayToken(array $data): ?string
    {
        $updatedAt = $data['updatedAt'] ?? $data['updated_at'] ?? null;
        if (!$updatedAt instanceof \DateTimeInterface && !is_string($updatedAt)) {
            return null;
        }

        $identifier = $data['id'] ?? $data['slug'] ?? md5((string) json_encode($data, JSON_THROW_ON_ERROR));

        $updatedAtValue = $updatedAt instanceof \DateTimeInterface
            ? $updatedAt->format(\DateTimeInterface::ATOM)
            : (string) $updatedAt;

        return $this->buildVersionToken((string) $identifier, $updatedAtValue);
    }

    private function buildVersionToken(string $identifier, string $updatedAt): string
    {
        return sprintf('%s|%s', $identifier, $updatedAt);
    }

    private function objectIdentifier(object $data): ?string
    {
        foreach (['getId', 'id', 'getSlug', 'slug'] as $method) {
            if (method_exists($data, $method)) {
                $value = $data->{$method}();

                return $value instanceof \Stringable ? (string) $value : (is_scalar($value) ? (string) $value : null);
            }
        }

        return null;
    }

    private function objectUpdatedAt(object $data): ?\DateTimeImmutable
    {
        foreach (['getUpdatedAt', 'updatedAt'] as $method) {
            if (!method_exists($data, $method)) {
                continue;
            }

            $value = $data->{$method}();
            if ($value instanceof \DateTimeImmutable) {
                return $value;
            }

            if ($value instanceof \DateTimeInterface) {
                return \DateTimeImmutable::createFromInterface($value);
            }

            if (is_string($value) && '' !== $value) {
                try {
                    return new \DateTimeImmutable($value);
                } catch (\Exception) {
                    return null;
                }
            }
        }

        if (property_exists($data, 'updatedAt') && $data->updatedAt instanceof \DateTimeImmutable) {
            return $data->updatedAt;
        }

        return null;
    }
}
