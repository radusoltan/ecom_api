<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class StorefrontCacheSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Priority -256 to run after API Platform's cache listener
            KernelEvents::RESPONSE => ['onKernelResponse', -256],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only apply to storefront API endpoints
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api/v1/storefront/') && !str_starts_with($path, '/api/storefront/')) {
            return;
        }

        // Don't cache error responses
        if ($response->getStatusCode() >= 400) {
            return;
        }

        $this->mergeVaryHeaders($response, ['Accept', 'Accept-Language', 'X-Tenant-ID']);

        // Set cache headers based on endpoint (check most specific paths first)
        if (str_contains($path, '/featured-products')) {
            // Featured products: Cache for 5 minutes, stale-while-revalidate for 10 minutes
            $response->headers->set('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
        } elseif (str_contains($path, '/home-categories')) {
            // Home categories: Cache for 5 minutes, stale-while-revalidate for 10 minutes
            $response->headers->set('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
        } elseif (str_contains($path, '/autocomplete') || str_contains($path, '/search')) {
            // Search and autocomplete: short TTL with fast revalidation
            $response->headers->set('Cache-Control', 'public, max-age=60, stale-while-revalidate=120');
        } elseif (str_contains($path, '/storefront/products')) {
            // Product listing: Cache for 5 minutes, stale-while-revalidate for 10 minutes
            $response->headers->set('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
        }

        // Add X-Content-Language header to indicate resolved locale
        $locale = $request->headers->get('Accept-Language', 'en');
        $resolvedLocale = $this->parseLocale($locale);
        $response->headers->set('X-Content-Language', $resolvedLocale);
    }

    private function parseLocale(string $acceptLanguage): string
    {
        $locales = explode(',', $acceptLanguage);
        if (empty($locales)) {
            return 'en';
        }

        $locale = explode(';', $locales[0])[0];

        return strtolower(trim($locale));
    }

    /**
     * @param array<string> $headers
     */
    private function mergeVaryHeaders(\Symfony\Component\HttpFoundation\Response $response, array $headers): void
    {
        $response->headers->set('Vary', implode(', ', array_values(array_unique([
            ...$response->getVary(),
            ...$headers,
        ]))));
    }
}
