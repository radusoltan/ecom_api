<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Internationalization\Infrastructure\ApiPlatform\Resource\TranslationResource;
use App\Shared\Domain\ValueObject\LanguageCode;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * API Platform State Provider for Translations
 *
 * Fetches translations from Symfony Translation component and exposes them via API.
 *
 * Query parameters:
 * - domain: Translation domain (messages, validators, emails) - defaults to 'messages'
 * - locale: Language code (en, fr, de) - defaults to request locale or 'en'
 *
 * Response format:
 * {
 *   "domain": "messages",
 *   "locale": "en",
 *   "translations": {
 *     "common.save": "Save",
 *     "common.cancel": "Cancel",
 *     "buttons.add_to_cart": "Add to Cart"
 *   }
 * }
 */
final readonly class TranslationsProvider implements ProviderInterface
{
    private const SUPPORTED_DOMAINS = ['messages', 'validators', 'emails'];
    private const DEFAULT_DOMAIN = 'messages';
    private const DEFAULT_LOCALE = 'en';

    public function __construct(
        private TranslatorInterface $translator,
        private RequestStack $requestStack,
        private KernelInterface $kernel,
    ) {
    }

    /**
     * @return TranslationResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();

        // Get domain from query parameter (default: 'messages')
        $requestedDomain = $request?->query->get('domain', self::DEFAULT_DOMAIN);
        $domain = $this->validateDomain($requestedDomain);

        // Get locale from query parameter or Accept-Language header
        $requestedLocale = $request?->query->get('locale')
            ?? $request?->getPreferredLanguage(LanguageCode::all())
            ?? self::DEFAULT_LOCALE;
        $locale = $this->validateLocale($requestedLocale);

        // Load translations directly from YAML file to avoid fallback behavior
        $translationsPath = $this->kernel->getProjectDir() . '/translations';
        $filename = sprintf('%s/%s.%s.yaml', $translationsPath, $domain, $locale);

        $messages = [];
        if (file_exists($filename)) {
            $messages = Yaml::parseFile($filename) ?? [];
        } else {
            // Fallback to default locale if file doesn't exist
            $fallbackFilename = sprintf('%s/%s.%s.yaml', $translationsPath, $domain, self::DEFAULT_LOCALE);
            if (file_exists($fallbackFilename)) {
                $messages = Yaml::parseFile($fallbackFilename) ?? [];
            }
        }

        // Flatten nested arrays with dot notation
        $flattenedTranslations = $this->flattenArray($messages);

        return [
            new TranslationResource(
                domain: $domain,
                locale: $locale,
                translations: $flattenedTranslations,
            ),
        ];
    }

    private function validateDomain(string $domain): string
    {
        if (!in_array($domain, self::SUPPORTED_DOMAINS, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid domain "%s". Supported domains: %s',
                    $domain,
                    implode(', ', self::SUPPORTED_DOMAINS)
                )
            );
        }

        return $domain;
    }

    private function validateLocale(string $locale): string
    {
        // Extract base language code (e.g., 'en' from 'en_US' or 'en-US')
        $baseLocale = strtok($locale, '_-');

        // LanguageCode::fromString() will throw InvalidArgumentException for unsupported locales
        $languageCode = LanguageCode::fromString($baseLocale);
        return $languageCode->value();
    }

    /**
     * Flatten nested array with dot notation
     * Example: ['common' => ['save' => 'Save']] becomes ['common.save' => 'Save']
     *
     * @param array<string, mixed> $array
     * @param string $prefix
     * @return array<string, string>
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = (string) $value;
            }
        }

        return $result;
    }
}
