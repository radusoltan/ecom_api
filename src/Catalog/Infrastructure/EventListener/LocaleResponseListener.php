<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\EventListener;

use App\Catalog\Domain\Service\LocalizationPolicy;
use App\Catalog\Domain\ValueObject\Locale;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event listener for handling locale resolution and response headers
 * Adds proper caching headers and content language indication.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 100)]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onKernelResponse')]
final class LocaleResponseListener
{
    private ?Locale $resolvedLocale = null;

    public function __construct(
        private readonly LocalizationPolicy $localizationPolicy
    ) {
    }

    /**
     * Handle request event to resolve locale early.
     */
    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Skip non-API routes
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        // Resolve locale from request
        $this->resolvedLocale = $this->resolveLocale($request);

        // Store locale in request attributes for later use
        $request->attributes->set('_locale', $this->resolvedLocale->toString());
        $request->attributes->set('_locale_object', $this->resolvedLocale);
    }

    /**
     * Handle response event to add proper headers.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Skip non-API routes
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        // Add Vary header for cache variation
        $varyHeaders = $response->headers->get('Vary', '');
        $varyArray = array_map('trim', explode(',', $varyHeaders));

        if (!in_array('Accept-Language', $varyArray, true)) {
            $varyArray[] = 'Accept-Language';
        }

        if (!in_array('X-Tenant-ID', $varyArray, true)) {
            $varyArray[] = 'X-Tenant-ID';
        }

        $response->headers->set('Vary', implode(', ', array_filter($varyArray)));

        // Add Content-Language header
        if (null !== $this->resolvedLocale) {
            $response->headers->set('X-Content-Language', $this->resolvedLocale->toString());

            // Also set standard Content-Language with language code only
            $response->headers->set('Content-Language', $this->resolvedLocale->getLanguageCode());
        }

        // Add cache control headers for translation responses
        if (str_contains($request->getPathInfo(), '/translations')
            || str_contains($request->getPathInfo(), '/products')
            || str_contains($request->getPathInfo(), '/categories')) {
            // Public cache for 1 hour, must revalidate
            $response->headers->set('Cache-Control', 'public, max-age=3600, must-revalidate');

            // Add ETag based on locale and tenant
            $tenantId = $request->headers->get('X-Tenant-ID', 'default');
            $etag = sprintf(
                '"%s-%s-%s"',
                md5($response->getContent() ?: ''),
                $this->resolvedLocale?->toString() ?? 'default',
                $tenantId
            );
            $response->headers->set('ETag', $etag);
        }

        // Add performance timing header
        if ($startTime = $request->attributes->get('_request_start_time')) {
            $duration = (microtime(true) - $startTime) * 1000;
            $response->headers->set('X-Response-Time', sprintf('%.2fms', $duration));
        }

        // Add locale debug information in development
        if ($_ENV['APP_ENV'] ?? 'prod' === 'dev') {
            $response->headers->set('X-Debug-Locale-Resolved', $this->resolvedLocale?->toString() ?? 'none');

            if ($request->headers->has('Accept-Language')) {
                $response->headers->set('X-Debug-Accept-Language', $request->headers->get('Accept-Language', ''));
            }
        }
    }

    /**
     * Resolve locale from request.
     */
    private function resolveLocale($request): Locale
    {
        // Priority 1: Query parameter
        if ($request->query->has('locale')) {
            try {
                return Locale::normalize($request->query->get('locale'));
            } catch (\InvalidArgumentException) {
                // Fall through to next priority
            }
        }

        // Priority 2: Request attribute (from route)
        if ($request->attributes->has('locale')) {
            try {
                return Locale::normalize($request->attributes->get('locale'));
            } catch (\InvalidArgumentException) {
                // Fall through to next priority
            }
        }

        // Priority 3: Accept-Language header
        if ($request->headers->has('Accept-Language')) {
            $acceptLanguage = $request->headers->get('Accept-Language', '');
            $locales = LocalizationPolicy::parseAcceptLanguageHeader($acceptLanguage);

            if (!empty($locales)) {
                // Check if locale is supported
                foreach ($locales as $locale) {
                    if ($this->localizationPolicy->isLocaleSupported($locale)) {
                        return $locale;
                    }
                }

                // Return first locale even if not explicitly supported
                return $locales[0];
            }
        }

        // Priority 4: User preference (if authenticated)
        if ($request->attributes->has('_user_preferred_locale')) {
            try {
                return Locale::normalize($request->attributes->get('_user_preferred_locale'));
            } catch (\InvalidArgumentException) {
                // Fall through to default
            }
        }

        // Priority 5: Default locale
        return Locale::fromString('en_US');
    }
}
