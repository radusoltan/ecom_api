<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
    private const DEFAULT_MAX_AGE = 300; // 5 minutes

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

        // Skip if cache explicitly disabled
        if ('true' === $request->headers->get('X-No-Cache')) {
            $response->headers->set('Cache-Control', 'no-cache, private');

            return;
        }

        // Skip caching for sensitive routes
        if ($this->isSensitiveRoute($request->getPathInfo())) {
            $response->headers->set('Cache-Control', 'no-cache, private');

            return;
        }

        // Determine max-age based on resource type
        $maxAge = $this->determineMaxAge($request->getPathInfo());

        // Set cache headers
        $response->setPublic();
        $response->setMaxAge($maxAge);
        $response->setSharedMaxAge($maxAge);

        // Add Vary header for content negotiation
        $response->headers->set(
            'Vary',
            'Accept, Accept-Language, X-Tenant-ID'
        );

        // Generate ETag for conditional requests
        $content = $response->getContent();
        if (false !== $content && '' !== $content) {
            $etag = md5($content);
            $response->setEtag($etag);

            // Handle If-None-Match for 304 responses
            if ($request->headers->get('If-None-Match') === $etag) {
                $response->setNotModified();
            }
        }

        // Add custom header for debugging
        $response->headers->set('X-Cache-TTL', (string) $maxAge);
    }

    /**
     * Check if route contains sensitive data that shouldn't be cached.
     */
    private function isSensitiveRoute(string $path): bool
    {
        $sensitivePatterns = [
            '/api/orders',
            '/api/payments',
            '/api/customers',
            '/api/returns',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine cache max-age based on resource type.
     */
    private function determineMaxAge(string $path): int
    {
        return match (true) {
            str_contains($path, '/products') => 300,      // 5 minutes
            str_contains($path, '/categories') => 600,    // 10 minutes
            str_contains($path, '/price_lists') => 300,   // 5 minutes
            str_contains($path, '/warehouses') => 600,    // 10 minutes
            str_contains($path, '/tenants') => 600,       // 10 minutes
            str_contains($path, '/promotions') => 300,    // 5 minutes
            default => self::DEFAULT_MAX_AGE,
        };
    }
}
