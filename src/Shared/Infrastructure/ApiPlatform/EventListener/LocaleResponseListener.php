<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\ApiPlatform\EventListener;

use App\Shared\Infrastructure\Locale\LocaleNegotiator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Locale Response Listener.
 *
 * Adds locale information to API responses via HTTP headers:
 * - X-Content-Language: Current locale used for response
 * - X-Supported-Languages: Comma-separated list of supported locales
 * - Content-Language: Standard HTTP header for content language
 *
 * This helps frontend applications:
 * 1. Know which locale was used for the response
 * 2. Display language switchers with available locales
 * 3. Cache responses per locale
 */
final readonly class LocaleResponseListener implements EventSubscriberInterface
{
    public function __construct(
        private LocaleNegotiator $localeNegotiator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run after the response is created but before it's sent
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // Only process main request
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only add headers to API routes
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        // Get current locale
        $currentLocale = $this->localeNegotiator->getCurrentLocale();
        $supportedLocales = $this->localeNegotiator->getSupportedLocales();
        $fallbackLocale = $this->localeNegotiator->getFallbackLocale();

        // Add locale headers
        $response->headers->set('X-Content-Language', $currentLocale);
        $response->headers->set('X-Fallback-Language', $fallbackLocale);
        $response->headers->set('X-Supported-Languages', implode(', ', $supportedLocales));

        // Standard HTTP Content-Language header
        $response->headers->set('Content-Language', $currentLocale);

        // Add Vary header to ensure proper caching
        $vary = $response->headers->get('Vary', '');
        $varyHeaders = array_filter(array_map('trim', explode(',', $vary)));

        if (!in_array('Accept-Language', $varyHeaders, true)) {
            $varyHeaders[] = 'Accept-Language';
        }

        $response->headers->set('Vary', implode(', ', $varyHeaders));
    }
}
