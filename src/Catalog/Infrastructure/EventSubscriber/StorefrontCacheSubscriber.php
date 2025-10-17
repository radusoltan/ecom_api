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
            KernelEvents::RESPONSE => ['onKernelResponse', -256]
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
        if (!str_starts_with($path, '/api/storefront/')) {
            return;
        }

        // Don't cache error responses
        if ($response->getStatusCode() >= 400) {
            return;
        }

        // Add Vary header for Accept-Language
        $response->headers->set('Vary', 'Accept-Language, X-Tenant-ID', false);

        // Set cache headers based on endpoint (check most specific paths first)
        if (str_contains($path, '/featured-products')) {
            // Featured products: Cache for 5 minutes, stale-while-revalidate for 10 minutes
            $response->headers->set('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
        } elseif (str_contains($path, '/home-categories')) {
            // Home categories: Cache for 5 minutes, stale-while-revalidate for 10 minutes
            $response->headers->set('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
        } elseif (str_contains($path, '/storefront/products')) {
            // Product listing: Cache for 2 minutes, stale-while-revalidate for 10 minutes
            $response->headers->set('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
        }

        // Add ETag for better cache validation
        $content = $response->getContent();
        if ($content) {
            $etag = md5($content);
            $response->headers->set('ETag', '"' . $etag . '"');

            // Check if client has the same version
            $clientEtag = $request->headers->get('If-None-Match');
            if ($clientEtag === '"' . $etag . '"') {
                $response->setNotModified();
            }
        }

        // Add Last-Modified header
        $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');

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
}