<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Serializer;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

final class MultipartDecoder implements DecoderInterface
{
    public const FORMAT = 'multipart';

    public function __construct(
        private readonly RequestStack $requestStack
    ) {}

    public function decode(string $data, string $format, array $context = []): ?array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return null;
        }

        $payload = $request->request->all();
        $decoded = [];

        foreach ($payload as $key => $value) {
            $decoded[$key] = $this->decodeValue($value);
        }

        return $decoded + $request->files->all();
    }

    public function supportsDecoding(string $format): bool
    {
        return self::FORMAT === $format;
    }

    private function decodeValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return $value;
        }

        try {
            return json_decode($value, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $value;
        }
    }
}
