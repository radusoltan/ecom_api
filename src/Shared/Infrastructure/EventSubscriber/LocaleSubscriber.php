<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventSubscriber;

use App\Shared\Infrastructure\Doctrine\Service\LocaleProvider;
use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Locale Subscriber.
 *
 * Automatically sets the locale for Gedmo Translatable behavior based on HTTP request.
 *
 * This subscriber listens to kernel requests and:
 * 1. Detects the current locale from request (query param or Accept-Language header)
 * 2. Sets the locale for Gedmo TranslatableListener
 * 3. Ensures all entity queries return translations in the correct language
 *
 * Locale detection priority:
 * 1. Query parameter: ?locale=fr
 * 2. Accept-Language header
 * 3. Default locale (en)
 */
final readonly class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TranslatableListener $translatableListener,
        private LocaleProvider $localeProvider,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Priority 20 ensures this runs after RouterListener (priority 32)
            // but before most application code
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    /**
     * Set Gedmo Translatable locale on every request.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        // Only process main request, not sub-requests
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $locale = $this->localeProvider->getCurrentLocale();

        // Set locale for Gedmo Translatable
        $this->translatableListener->setTranslatableLocale($locale);

        // Set request locale for Symfony (used by translator component)
        $request->setLocale($locale);
    }
}
