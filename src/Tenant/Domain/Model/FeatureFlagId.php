<?php

declare(strict_types=1);

namespace App\Tenant\Domain\Model;

final readonly class FeatureFlagId implements \Stringable
{
    private function __construct(private string $value)
    {
        if ('' === $value) {
            throw new \InvalidArgumentException('FeatureFlagId cannot be empty');
        }

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            throw new \InvalidArgumentException(sprintf('Invalid FeatureFlagId format: "%s". Must be a valid UUID v4', $value));
        }
    }

    public static function generate(): self
    {
        return new self(self::generateUuidV4());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function generateUuidV4(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0F | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
