<?php

declare(strict_types=1);

namespace App\Internationalization\Domain\Event;

use App\Internationalization\Domain\Model\TranslationId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class TranslationUpdated
{
    public function __construct(
        public TranslationId $translationId,
        public TenantId $tenantId,
        public string $domain,
        public string $key,
        public string $locale,
    ) {
    }
}
