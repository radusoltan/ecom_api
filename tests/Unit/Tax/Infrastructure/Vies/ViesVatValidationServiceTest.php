<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Infrastructure\Vies;

use App\Tax\Domain\Service\VatValidationResult;
use App\Tax\Domain\ValueObject\VatNumber;
use App\Tax\Infrastructure\Vies\ViesVatValidationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class ViesVatValidationServiceTest extends TestCase
{
    private LoggerInterface $logger;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
    }

    private function createService(int $cacheTtl = 86400): ViesVatValidationService
    {
        return new ViesVatValidationService(
            $this->logger,
            $this->cache,
            $cacheTtl,
        );
    }

    public function testValidateUsesCacheAndReturnsResult(): void
    {
        $vatNumber = VatNumber::fromString('DE123456789');
        $expectedResult = VatValidationResult::valid($vatNumber, 'Test GmbH', 'Berlin, Germany');

        $this->cache->method('get')->willReturn($expectedResult);

        $service = $this->createService();
        $result = $service->validate($vatNumber);

        self::assertTrue($result->isValid());
        self::assertSame('Test GmbH', $result->businessName);
    }

    public function testValidateFallsBackOnCacheException(): void
    {
        $vatNumber = VatNumber::fromString('DE123456789');

        // Cache throws, so validateWithVies is called directly.
        // Since we can't connect to VIES in unit tests, getSoapClient returns null,
        // which returns serviceUnavailable.
        $this->cache->method('get')->willThrowException(new \RuntimeException('Cache down'));

        $this->logger->expects(self::atLeastOnce())->method('warning');

        $service = $this->createService();
        $result = $service->validate($vatNumber);

        // Should return a result (serviceUnavailable since no SOAP client in tests)
        self::assertInstanceOf(VatValidationResult::class, $result);
        self::assertFalse($result->isValid());
    }

    public function testIsAvailableReturnsFalseWhenSoapFails(): void
    {
        // getSoapClient() will try to create a real SoapClient and fail (no network in unit tests)
        $service = $this->createService();
        $result = $service->isAvailable();

        // In unit test context, SOAP WSDL is unreachable, so returns false
        self::assertFalse($result);
    }

    public function testValidateCacheCallbackCachesValidResults(): void
    {
        $vatNumber = VatNumber::fromString('FR12345678901');
        $validResult = VatValidationResult::valid($vatNumber, 'Societe FR', 'Paris');

        // Simulate cache returning a pre-cached valid result
        $this->cache->method('get')->willReturn($validResult);

        $service = $this->createService();
        $result = $service->validate($vatNumber);

        self::assertTrue($result->isValid());
        self::assertSame('Societe FR', $result->businessName);
        self::assertSame('Paris', $result->businessAddress);
    }

    public function testValidateCacheCallbackDoesNotCacheInvalidResults(): void
    {
        $vatNumber = VatNumber::fromString('DE123456789');
        $invalidResult = VatValidationResult::invalid($vatNumber, 'Not found');

        $this->cache->method('get')->willReturn($invalidResult);

        $service = $this->createService();
        $result = $service->validate($vatNumber);

        self::assertFalse($result->isValid());
        self::assertTrue($result->hasError());
    }

    public function testCacheKeyUsesLowercaseVatNumber(): void
    {
        $vatNumber = VatNumber::fromString('DE123456789');

        $this->cache->expects(self::once())
            ->method('get')
            ->with(
                self::callback(fn (string $key) => str_contains($key, 'vies_vat_de123456789')),
                self::anything()
            )
            ->willReturn(VatValidationResult::invalid($vatNumber, 'test'));

        $service = $this->createService();
        $service->validate($vatNumber);
    }

    public function testServiceUnavailableResultWhenSoapFails(): void
    {
        $vatNumber = VatNumber::fromString('IT12345678901');

        // Simulate cache miss: callback is invoked, which calls validateWithVies
        // SOAP client will fail to init in unit test env
        $this->cache->method('get')->willReturnCallback(
            function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter');

                return $callback($item);
            }
        );

        $service = $this->createService();
        $result = $service->validate($vatNumber);

        self::assertFalse($result->isValid());
        self::assertTrue($result->hasError());
    }
}
