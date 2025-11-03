<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Infrastructure\Parser;

use App\Internationalization\Infrastructure\Parser\JSONTranslationParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Internationalization\Infrastructure\Parser\JSONTranslationParser
 */
final class JSONTranslationParserTest extends TestCase
{
    private JSONTranslationParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JSONTranslationParser();
    }

    public function testParseString_ValidJSON_ReturnsArray(): void
    {
        $json = json_encode([
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
            ['locale' => 'fr', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Bonjour'],
            ['locale' => 'de', 'domain' => 'validators', 'key' => 'email.invalid', 'value' => 'Invalid email'],
        ]);

        $result = $this->parser->parseString($json);

        $this->assertCount(3, $result);
        $this->assertEquals([
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
            ['locale' => 'fr', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Bonjour'],
            ['locale' => 'de', 'domain' => 'validators', 'key' => 'email.invalid', 'value' => 'Invalid email'],
        ], $result);
    }

    public function testParseString_InvalidJSON_ThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid JSON/');

        $this->parser->parseString('not valid json');
    }

    public function testParseString_NotArray_ThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/JSON must be an array|Item at index/');

        $this->parser->parseString('{"locale": "en"}');
    }

    public function testParseString_ItemNotObject_ThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Item at index 0 is not an object');

        $this->parser->parseString('["string"]');
    }

    public function testParseString_MissingLocale_ThrowsException(): void
    {
        $json = json_encode([
            ['domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing required field "locale"');

        $this->parser->parseString($json);
    }

    public function testParseString_MissingDomain_ThrowsException(): void
    {
        $json = json_encode([
            ['locale' => 'en', 'key' => 'hello', 'value' => 'Hello'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing required field "domain"');

        $this->parser->parseString($json);
    }

    public function testParseString_MissingKey_ThrowsException(): void
    {
        $json = json_encode([
            ['locale' => 'en', 'domain' => 'messages', 'value' => 'Hello'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing required field "key"');

        $this->parser->parseString($json);
    }

    public function testParseString_MissingValue_ThrowsException(): void
    {
        $json = json_encode([
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing required field "value"');

        $this->parser->parseString($json);
    }

    public function testParseString_FieldNotString_ThrowsException(): void
    {
        $json = json_encode([
            ['locale' => 123, 'domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('field "locale" must be a string');

        $this->parser->parseString($json);
    }

    public function testParseString_EmptyArray_ThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No translations found in JSON file');

        $this->parser->parseString('[]');
    }

    public function testGenerateJSON_ValidData_ReturnsJSONString(): void
    {
        $data = [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
            ['locale' => 'fr', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Bonjour'],
        ];

        $result = $this->parser->generateJSON($data, prettyPrint: false);

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertEquals($data, $decoded);
    }

    public function testGenerateJSON_PrettyPrint_FormatsWithIndentation(): void
    {
        $data = [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
        ];

        $result = $this->parser->generateJSON($data, prettyPrint: true);

        $this->assertStringContainsString("\n", $result);
        $this->assertStringContainsString('    ', $result); // Indentation
    }

    public function testGenerateJSON_PreservesUnicode(): void
    {
        $data = [
            ['locale' => 'ro', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Bună ziua'],
        ];

        $result = $this->parser->generateJSON($data);

        $this->assertStringContainsString('Bună ziua', $result);
        $this->assertStringNotContainsString('\\u', $result); // Not escaped
    }

    public function testRoundTrip_ParseAndGenerate_PreservesData(): void
    {
        $original = [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
            ['locale' => 'fr', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Bonjour'],
        ];

        $json = $this->parser->generateJSON($original);
        $parsed = $this->parser->parseString($json);

        $this->assertEquals($original, $parsed);
    }
}
