<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Middleware;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * IdempotencyMiddleware.
 *
 * Ensures idempotent POST requests by caching responses based on Idempotency-Key header.
 * Implements best practices from Stripe's idempotency approach.
 *
 * Business Rules:
 * - Only applies to POST requests with Idempotency-Key header
 * - Key format: client-generated UUID or unique string
 * - Cache TTL: 24 hours (86400 seconds)
 * - Tenant isolation: keys namespaced by tenant ID
 * - Payload validation: same key with different payload returns 422
 *
 * @see https://stripe.com/docs/api/idempotent_requests
 */
final class IdempotencyMiddleware
{
    private const HEADER_NAME = 'Idempotency-Key';
    private const CACHE_TTL = 86400; // 24 hours
    private const CACHE_PREFIX = 'idempotency';

    /** @var list<string> Paths requiring Idempotency-Key on POST/PUT/PATCH */
    private const REQUIRED_PATHS = [
        '/api/v1/orders',
        '/api/v1/payments',
        '/api/v1/refunds',
        '/api/v1/shipments',
    ];

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $method = $request->getMethod();

        // Only process mutation methods (POST, PUT, PATCH)
        if (!in_array($method, [Request::METHOD_POST, Request::METHOD_PUT, Request::METHOD_PATCH], true)) {
            return;
        }

        $idempotencyKey = $request->headers->get(self::HEADER_NAME);

        // Enforce Idempotency-Key on critical endpoints
        if (!$idempotencyKey && $this->requiresIdempotencyKey($request)) {
            throw new BadRequestHttpException('Missing required Idempotency-Key header for this endpoint.');
        }

        if (!$idempotencyKey) {
            return;
        }

        // Validate idempotency key format (alphanumeric, hyphens, max 255 chars)
        if (!$this->isValidIdempotencyKey($idempotencyKey)) {
            $this->logger->warning('Invalid idempotency key format', [
                'key' => $idempotencyKey,
                'path' => $request->getPathInfo(),
            ]);

            return;
        }

        $tenantId = $request->headers->get('X-Tenant-ID', 'default');
        $cacheKey = $this->buildCacheKey($tenantId, $idempotencyKey);
        $requestPayload = $request->getContent();
        $requestHash = hash('sha256', $requestPayload);

        try {
            $cachedData = $this->cache->get($cacheKey, function (ItemInterface $item) {
                // No cached response yet, allow request to proceed
                return null;
            });

            if (null !== $cachedData) {
                // Check if payload matches
                if ($cachedData['request_hash'] !== $requestHash) {
                    $this->logger->warning('Idempotency key reused with different payload', [
                        'key' => $idempotencyKey,
                        'tenant_id' => $tenantId,
                        'path' => $request->getPathInfo(),
                    ]);

                    $response = new Response(
                        json_encode([
                            'type' => 'https://tools.ietf.org/html/rfc7231#section-6.5.1',
                            'title' => 'Idempotency key conflict',
                            'status' => 422,
                            'detail' => 'The provided idempotency key has been used with a different request payload.',
                        ]),
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        ['Content-Type' => 'application/problem+json']
                    );
                    $event->setResponse($response);

                    return;
                }

                // Return cached response
                $this->logger->info('Idempotency: reused cached response', [
                    'key' => $idempotencyKey,
                    'tenant_id' => $tenantId,
                    'path' => $request->getPathInfo(),
                    'cached_at' => $cachedData['cached_at'],
                ]);

                $response = new Response(
                    $cachedData['content'],
                    $cachedData['status_code'],
                    array_merge(
                        $cachedData['headers'],
                        ['X-Idempotency-Replay' => 'true']
                    )
                );
                $event->setResponse($response);

                return;
            }

            // Store request hash for validation when caching response
            $request->attributes->set('_idempotency_key', $idempotencyKey);
            $request->attributes->set('_idempotency_request_hash', $requestHash);
            $request->attributes->set('_idempotency_tenant_id', $tenantId);
        } catch (\Exception $e) {
            $this->logger->error('Idempotency middleware error', [
                'error' => $e->getMessage(),
                'key' => $idempotencyKey,
            ]);
            // On error, allow request to proceed without idempotency
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $idempotencyKey = $request->attributes->get('_idempotency_key');
        if (!$idempotencyKey) {
            return;
        }

        // Only cache successful responses (2xx status codes)
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return;
        }

        $tenantId = $request->attributes->get('_idempotency_tenant_id');
        $requestHash = $request->attributes->get('_idempotency_request_hash');
        $cacheKey = $this->buildCacheKey($tenantId, $idempotencyKey);

        try {
            $this->cache->get($cacheKey, function (ItemInterface $item) use ($response, $requestHash) {
                $item->expiresAfter(self::CACHE_TTL);

                return [
                    'status_code' => $response->getStatusCode(),
                    'headers' => $this->getSerializableHeaders($response),
                    'content' => $response->getContent(),
                    'request_hash' => $requestHash,
                    'cached_at' => (new \DateTimeImmutable())->format('c'),
                ];
            });

            $this->logger->info('Idempotency: cached response', [
                'key' => $idempotencyKey,
                'tenant_id' => $tenantId,
                'status_code' => $response->getStatusCode(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to cache idempotent response', [
                'error' => $e->getMessage(),
                'key' => $idempotencyKey,
            ]);
            // Continue without caching on error
        }
    }

    private function requiresIdempotencyKey(Request $request): bool
    {
        $path = $request->getPathInfo();
        foreach (self::REQUIRED_PATHS as $requiredPath) {
            if (str_starts_with($path, $requiredPath)) {
                return true;
            }
        }

        return false;
    }

    private function isValidIdempotencyKey(string $key): bool
    {
        // Allow alphanumeric, hyphens, underscores, max 255 chars
        return 1 === preg_match('/^[a-zA-Z0-9_-]{1,255}$/', $key);
    }

    private function buildCacheKey(string $tenantId, string $idempotencyKey): string
    {
        return sprintf(
            '%s:%s:%s',
            self::CACHE_PREFIX,
            $tenantId,
            $idempotencyKey
        );
    }

    private function getSerializableHeaders(Response $response): array
    {
        $headers = [];
        foreach ($response->headers->all() as $name => $values) {
            // Skip headers that shouldn't be cached
            if (in_array(strtolower($name), ['set-cookie', 'date', 'age'], true)) {
                continue;
            }
            $headers[$name] = $values;
        }

        return $headers;
    }
}
