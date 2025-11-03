<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Infrastructure\Parser;

use App\Internationalization\Infrastructure\Parser\CSVTranslationParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Internationalization\Infrastructure\Parser\CSVTranslationParser
 */
final class CSVTranslationParserTest extends TestCase
{
    private CSVTranslationParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CSVTranslationParser();
    }

    public function testParseString_ValidCSV_ReturnsArray(): void
    {
        $csv = <<<CSV
locale,domain,key,value
en,messages,hello,Hello
fr,messages,hello,Bonjour
de,validators,email.invalid,Invalid email
CSV;

        $result = $this->parser->parseString($csv);

        $this->assertCount(3, $result);
        $this->assertEquals([
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
            ['locale' => 'fr', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Bonjour'],
            ['locale' => 'de', 'domain' => 'validators', 'key' => 'email.invalid', 'value' => 'Invalid email'],
        ], $result);
    }

    public function testParseString_WithoutHeader_ReturnsArray(): void
    {
        $csv = <<<CSV
en,messages,hello,Hello
fr,messages,hello,Bonjour
CSV;

        $result = $this->parser->parseString($csv);

        $this->assertCount(2, $result);
        $this->assertEquals('en', $result[0]['locale']);
        $this->assertEquals('fr', $result[1]['locale']);
    }

    public function testParseString_WithQuotedValues_HandlesCorrectly(): void
    {
        $csv = <<<CSV
en,messages,message,"Hello, World!"
en,messages,quote,"He said ""Hi"""
CSV;

        $result = $this->parser->parseString($csv);

        $this->assertCount(2, $result);
        $this->assertEquals('Hello, World!', $result[0]['value']);
        $this->assertEquals('He said "Hi"', $result[1]['value']);
    }

    public function testParseString_EmptyLines_SkipsEmpty(): void
    {
        $csv = <<<CSV
locale,domain,key,value
en,messages,hello,Hello

fr,messages,hello,Bonjour
CSV;

        $result = $this->parser->parseString($csv);

        $this->assertCount(2, $result);
    }

    public function testParseString_InvalidColumnCount_ThrowsException(): void
    {
        $csv = <<<CSV
en,messages,hello
CSV;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected 4 columns, got 3');

        $this->parser->parseString($csv);
    }

    public function testParseString_EmptyContent_ThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No translations found in CSV content');

        $this->parser->parseString('');
    }

    public function testGenerateCSV_ValidData_ReturnsCSVString(): void
    {
        $data = [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
            ['locale' => 'fr', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Bonjour'],
        ];

        $result = $this->parser->generateCSV($data, includeHeader: true);

        $this->assertStringContainsString('locale,domain,key,value', $result);
        $this->assertStringContainsString('en,messages,hello,Hello', $result);
        $this->assertStringContainsString('fr,messages,hello,Bonjour', $result);
    }

    public function testGenerateCSV_WithoutHeader_NoHeaderLine(): void
    {
        $data = [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
        ];

        $result = $this->parser->generateCSV($data, includeHeader: false);

        $this->assertStringNotContainsString('locale,domain,key,value', $result);
        $this->assertStringContainsString('en,messages,hello,Hello', $result);
    }

    public function testGenerateCSV_ValueWithComma_QuotesValue(): void
    {
        $data = [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'message', 'value' => 'Hello, World!'],
        ];

        $result = $this->parser->generateCSV($data);

        $this->assertStringContainsString('"Hello, World!"', $result);
    }

    public function testGenerateCSV_ValueWithQuotes_EscapesQuotes(): void
    {
        $data = [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'quote', 'value' => 'He said "Hi"'],
        ];

        $result = $this->parser->generateCSV($data);

        $this->assertStringContainsString('"He said ""Hi"""', $result);
    }

    public function testRoundTrip_ParseAndGenerate_PreservesData(): void
    {
        $original = [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
            ['locale' => 'fr', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Bonjour'],
        ];

        $csv = $this->parser->generateCSV($original, includeHeader: true);
        $parsed = $this->parser->parseString($csv);

        $this->assertEquals($original, $parsed);
    }
}
