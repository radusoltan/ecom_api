<?php

declare(strict_types=1);

namespace App\Shared\Application\Service;

use App\Shared\Domain\ValueObject\LanguageCode;

final class TranslationService
{
    private LanguageCode $currentLanguage;

    public function __construct()
    {
        $this->currentLanguage = LanguageCode::default();
    }

    public function setCurrentLanguage(LanguageCode $language): void
    {
        $this->currentLanguage = $language;
    }

    public function getCurrentLanguage(): LanguageCode
    {
        return $this->currentLanguage;
    }

    public function getCurrentLanguageCode(): string
    {
        return $this->currentLanguage->toString();
    }

    public function isDefaultLanguage(): bool
    {
        return $this->currentLanguage->isDefault();
    }

    /**
     * @return array<string>
     */
    public function getSupportedLanguages(): array
    {
        return LanguageCode::supportedLanguages();
    }
}
