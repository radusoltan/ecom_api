<?php

declare(strict_types=1);

namespace App\Payment\Domain\Model;

use Symfony\Component\Uid\Uuid;

final readonly class WebhookEventId
{
    private function __construct(
        private string $value,
    ) {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid WebhookEventId format: "%s". Must be a valid UUID.', $value));
        }
    }

    public static function generate(): self
    {
        return new self((string) Uuid::v4());
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
}
