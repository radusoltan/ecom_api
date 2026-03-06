<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Query;

use App\Catalog\Application\Query\GetAllProductOptions;
use App\Catalog\Application\Query\GetAllProductOptionsHandler;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('catalog')]
final class GetAllProductOptionsHandlerTest extends TestCase
{
    private Connection&MockObject $connection;
    private GetAllProductOptionsHandler $handler;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->handler = new GetAllProductOptionsHandler($this->connection);
    }

    public function testReturnsEmptyArrayWhenNoResults(): void
    {
        $query = new GetAllProductOptions(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
        );

        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = ($this->handler)($query);

        self::assertSame([], $result);
    }

    public function testGroupsOptionsAndValues(): void
    {
        $query = new GetAllProductOptions(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
        );

        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'option_code' => 'color',
                'option_name_translations' => '{"en":"Color","de":"Farbe"}',
                'value_code' => 'red',
                'value_name_translations' => '{"en":"Red","de":"Rot"}',
                'value_position' => '1',
            ],
            [
                'option_code' => 'color',
                'option_name_translations' => '{"en":"Color","de":"Farbe"}',
                'value_code' => 'blue',
                'value_name_translations' => '{"en":"Blue","de":"Blau"}',
                'value_position' => '2',
            ],
            [
                'option_code' => 'size',
                'option_name_translations' => '{"en":"Size"}',
                'value_code' => 'large',
                'value_name_translations' => '{"en":"Large"}',
                'value_position' => '1',
            ],
        ]);

        $result = ($this->handler)($query);

        self::assertCount(2, $result);

        // Color option
        self::assertSame('color', $result[0]->code);
        self::assertCount(2, $result[0]->values);
        self::assertSame('red', $result[0]->values[0]->code);
        self::assertSame('blue', $result[0]->values[1]->code);

        // Size option
        self::assertSame('size', $result[1]->code);
        self::assertCount(1, $result[1]->values);
    }

    public function testDeduplicatesValues(): void
    {
        $query = new GetAllProductOptions(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
        );

        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'option_code' => 'color',
                'option_name_translations' => '{"en":"Color"}',
                'value_code' => 'red',
                'value_name_translations' => '{"en":"Red"}',
                'value_position' => '1',
            ],
            [
                'option_code' => 'color',
                'option_name_translations' => '{"en":"Color"}',
                'value_code' => 'red',
                'value_name_translations' => '{"en":"Red"}',
                'value_position' => '1',
            ],
        ]);

        $result = ($this->handler)($query);

        self::assertCount(1, $result);
        self::assertCount(1, $result[0]->values);
    }

    public function testSortsValuesByPosition(): void
    {
        $query = new GetAllProductOptions(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
        );

        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'option_code' => 'size',
                'option_name_translations' => '{"en":"Size"}',
                'value_code' => 'xl',
                'value_name_translations' => '{"en":"XL"}',
                'value_position' => '3',
            ],
            [
                'option_code' => 'size',
                'option_name_translations' => '{"en":"Size"}',
                'value_code' => 'sm',
                'value_name_translations' => '{"en":"Small"}',
                'value_position' => '1',
            ],
            [
                'option_code' => 'size',
                'option_name_translations' => '{"en":"Size"}',
                'value_code' => 'md',
                'value_name_translations' => '{"en":"Medium"}',
                'value_position' => '2',
            ],
        ]);

        $result = ($this->handler)($query);

        self::assertSame('sm', $result[0]->values[0]->code);
        self::assertSame('md', $result[0]->values[1]->code);
        self::assertSame('xl', $result[0]->values[2]->code);
    }

    public function testWithLocaleParameter(): void
    {
        $query = new GetAllProductOptions(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            'de',
        );

        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'option_code' => 'color',
                'option_name_translations' => '{"en":"Color","de":"Farbe"}',
                'value_code' => 'red',
                'value_name_translations' => '{"en":"Red","de":"Rot"}',
                'value_position' => '1',
            ],
        ]);

        $result = ($this->handler)($query);

        self::assertCount(1, $result);
        self::assertSame('color', $result[0]->code);
    }
}
