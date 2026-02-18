<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Encryption;

use App\Shared\Infrastructure\Doctrine\Type\EncryptedJsonType;
use App\Shared\Infrastructure\Doctrine\Type\EncryptedStringType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Injects EncryptionService into Doctrine custom types on first request.
 *
 * Doctrine types are singletons that cannot use constructor injection.
 * This listener runs early (priority 255) to inject the service via static setters
 * before any Doctrine queries execute.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 255)]
final readonly class EncryptionTypeBootListener
{
    public function __construct(
        private EncryptionService $encryptionService,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        EncryptedStringType::setEncryptionService($this->encryptionService);
        EncryptedJsonType::setEncryptionService($this->encryptionService);
    }
}
