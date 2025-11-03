<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\Parser;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * JSON Translation Parser
 *
 * Parses JSON files to translation array format.
 * Expected JSON format: [{locale, domain, key, value}, ...]
 *
 * Features:
 * - JSON schema validation
 * - UTF-8 encoding support
 * - Detailed error messages
 * - Pretty print support for export
 */
final class JSONTranslationParser
{
    /**
     * Parse JSON file to array format
     *
     * @param UploadedFile $file
     * @return array<array{locale: string, domain: string, key: string, value: string}>
     * @throws \RuntimeException If file cannot be read or parsed
     */
    public function parse(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('Invalid file upload');
        }

        $filePath = $file->getRealPath();
        if ($filePath === false) {
            throw new \RuntimeException('Could not read uploaded file');
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException('Could not read file contents');
        }

        return $this->parseString($content);
    }

    /**
     * Parse JSON content from string
     *
     * @param string $content JSON content
     * @return array<array{locale: string, domain: string, key: string, value: string}>
     * @throws \RuntimeException If JSON is invalid
     */
    public function parseString(string $content): array
    {
        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(sprintf(
                'Invalid JSON: %s',
                $e->getMessage()
            ));
        }

        if (!is_array($data)) {
            throw new \RuntimeException('JSON must be an array of translation objects');
        }

        $translations = [];

        foreach ($data as $index => $item) {
            if (!is_array($item)) {
                throw new \RuntimeException(sprintf(
                    'Item at index %d is not an object',
                    $index
                ));
            }

            // Validate required fields
            $requiredFields = ['locale', 'domain', 'key', 'value'];
            foreach ($requiredFields as $field) {
                if (!isset($item[$field])) {
                    throw new \RuntimeException(sprintf(
                        'Item at index %d is missing required field "%s"',
                        $index,
                        $field
                    ));
                }

                if (!is_string($item[$field])) {
                    throw new \RuntimeException(sprintf(
                        'Item at index %d: field "%s" must be a string',
                        $index,
                        $field
                    ));
                }
            }

            $translations[] = [
                'locale' => trim($item['locale']),
                'domain' => trim($item['domain']),
                'key' => trim($item['key']),
                'value' => trim($item['value']),
            ];
        }

        if (empty($translations)) {
            throw new \RuntimeException('No translations found in JSON file');
        }

        return $translations;
    }

    /**
     * Generate JSON content from translations array
     *
     * @param array<array{locale: string, domain: string, key: string, value: string}> $translations
     * @param bool $prettyPrint Whether to format JSON with indentation
     */
    public function generateJSON(array $translations, bool $prettyPrint = true): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        try {
            return json_encode($translations, $flags | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(sprintf(
                'Failed to generate JSON: %s',
                $e->getMessage()
            ));
        }
    }
}
