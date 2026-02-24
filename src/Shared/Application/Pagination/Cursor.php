<?php

declare(strict_types=1);

namespace App\Shared\Application\Pagination;

/**
 * Value object representing a cursor for keyset pagination.
 *
 * Cursors encode the position in a result set as base64-encoded JSON
 * containing the row's ID and sort field value. This enables efficient
 * seek-based pagination without offset scanning.
 */
final readonly class Cursor
{
    public function __construct(
        public string $id,
        public string|int|float|null $sortValue,
        public string $sortField,
    ) {
    }

    /**
     * Encode a cursor from pagination state.
     */
    public static function encode(string $id, string|int|float|null $sortValue, string $sortField = 'id'): string
    {
        return base64_encode((string) json_encode([
            'id' => $id,
            'sf' => $sortField,
            'sv' => $sortValue,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Decode a cursor string back to a Cursor object.
     *
     * @throws \InvalidArgumentException if the cursor is malformed
     */
    public static function decode(string $cursor): self
    {
        $json = base64_decode($cursor, true);
        if (false === $json) {
            throw new \InvalidArgumentException('Invalid cursor: not valid base64');
        }

        try {
            /** @var array{id: string, sf: string, sv: string|int|float|null} $data */
            $data = json_decode($json, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Invalid cursor: not valid JSON', 0, $e);
        }

        if (!isset($data['id'], $data['sf'])) {
            throw new \InvalidArgumentException('Invalid cursor: missing required fields');
        }

        return new self(
            id: $data['id'],
            sortValue: $data['sv'] ?? null,
            sortField: $data['sf'],
        );
    }
}
