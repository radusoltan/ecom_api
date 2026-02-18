<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Vies;

use App\Tax\Domain\Service\VatValidationResult;
use App\Tax\Domain\Service\VatValidationServiceInterface;
use App\Tax\Domain\ValueObject\VatNumber;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * VIES VAT Validation Service Implementation.
 *
 * Validates EU VAT numbers against the European Commission's VIES system.
 * VIES = VAT Information Exchange System
 *
 * Features:
 * - SOAP API integration with EC VIES service
 * - 24-hour caching for valid VAT numbers
 * - Graceful error handling (service unavailability)
 * - Comprehensive logging for monitoring
 * - Fallback mechanism when VIES is down
 *
 * VIES SOAP API:
 * - WSDL: https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl
 * - Returns: validity + business name + business address
 * - Rate limits: Member states may have concurrent request limits
 * - Maintenance: VIES may be unavailable on weekends
 *
 * Caching Strategy:
 * - Cache valid results for 24 hours (VAT registrations don't change often)
 * - Don't cache invalid results (might be temporary input errors)
 * - Don't cache service unavailable results (transient failures)
 *
 * Error Handling:
 * - INVALID_INPUT: Malformed VAT number
 * - SERVICE_UNAVAILABLE: VIES system down
 * - MS_UNAVAILABLE: Member state service unavailable
 * - TIMEOUT: Request timeout
 * - MS_MAX_CONCURRENT_REQ: Rate limited by member state
 *
 * Business Rules:
 * - Greece uses 'EL' country code in VIES (not 'GR')
 * - Validation doesn't block checkout (use unverified status)
 * - Log all service failures for monitoring
 */
final class ViesVatValidationService implements VatValidationServiceInterface
{
    private const VIES_WSDL = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';
    private const CACHE_TTL = 86400; // 24 hours
    private const CACHE_KEY_PREFIX = 'vies_vat_';
    private const SOAP_TIMEOUT = 10; // seconds

    private ?\SoapClient $soapClient = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
        private readonly int $cacheTtl = self::CACHE_TTL,
    ) {
    }

    public function validate(VatNumber $vatNumber): VatValidationResult
    {
        $cacheKey = $this->getCacheKey($vatNumber);

        try {
            // Try to get cached result first
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($vatNumber): VatValidationResult {
                $this->logger->info('VIES: Validating VAT number (cache miss)', [
                    'vat_number' => $vatNumber->getFull(),
                    'country_code' => $vatNumber->getCountryCode(),
                ]);

                // Perform actual VIES validation
                $result = $this->validateWithVies($vatNumber);

                // Only cache valid results
                if ($result->isValid()) {
                    $item->expiresAfter($this->cacheTtl);
                    $this->logger->info('VIES: Caching valid VAT result', [
                        'vat_number' => $vatNumber->getFull(),
                        'ttl' => $this->cacheTtl,
                    ]);
                } else {
                    // Don't cache invalid or error results
                    $item->expiresAfter(0);
                }

                return $result;
            });
        } catch (\Throwable $e) {
            // Cache operation failed, fall back to direct validation
            $this->logger->warning('VIES: Cache error, falling back to direct validation', [
                'error' => $e->getMessage(),
                'vat_number' => $vatNumber->getFull(),
            ]);

            return $this->validateWithVies($vatNumber);
        }
    }

    public function isAvailable(): bool
    {
        try {
            $client = $this->getSoapClient();

            return null !== $client;
        } catch (\Throwable $e) {
            $this->logger->warning('VIES: Service availability check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function validateWithVies(VatNumber $vatNumber): VatValidationResult
    {
        try {
            $client = $this->getSoapClient();

            if (null === $client) {
                $this->logger->error('VIES: SOAP client initialization failed', [
                    'vat_number' => $vatNumber->getFull(),
                ]);

                return VatValidationResult::serviceUnavailable(
                    $vatNumber,
                    'VIES service is currently unavailable'
                );
            }

            // Greece uses 'EL' in VIES instead of 'GR'
            $countryCode = $vatNumber->getCountryCode();
            if ('GR' === $countryCode) {
                $countryCode = 'EL';
            }

            // Call VIES checkVat operation
            $response = $client->checkVat([
                'countryCode' => $countryCode,
                'vatNumber' => $vatNumber->getNumber(),
            ]);

            if ($response->valid) {
                $this->logger->info('VIES: VAT number validated successfully', [
                    'vat_number' => $vatNumber->getFull(),
                    'business_name' => $response->name ?? 'N/A',
                ]);

                return VatValidationResult::valid(
                    $vatNumber,
                    $response->name ?? null,
                    $response->address ?? null,
                    $response->requestDate ?? null
                );
            }

            $this->logger->info('VIES: VAT number is invalid', [
                'vat_number' => $vatNumber->getFull(),
            ]);

            return VatValidationResult::invalid(
                $vatNumber,
                'VAT number not found in VIES database',
                $response->requestDate ?? null
            );
        } catch (\SoapFault $e) {
            return $this->handleSoapFault($e, $vatNumber);
        } catch (\Throwable $e) {
            $this->logger->error('VIES: Unexpected error during validation', [
                'vat_number' => $vatNumber->getFull(),
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return VatValidationResult::serviceUnavailable(
                $vatNumber,
                sprintf('Validation failed: %s', $e->getMessage())
            );
        }
    }

    private function handleSoapFault(\SoapFault $fault, VatNumber $vatNumber): VatValidationResult
    {
        $faultString = $fault->faultstring ?? 'Unknown SOAP error';
        $faultCode = $fault->faultcode ?? 'UNKNOWN';

        $this->logger->warning('VIES: SOAP fault occurred', [
            'vat_number' => $vatNumber->getFull(),
            'fault_code' => $faultCode,
            'fault_string' => $faultString,
        ]);

        // Map known VIES fault codes to user-friendly messages
        $errorMessage = match (true) {
            str_contains($faultString, 'INVALID_INPUT') => 'Invalid VAT number format',
            str_contains($faultString, 'SERVICE_UNAVAILABLE') => 'VIES service is temporarily unavailable',
            str_contains($faultString, 'MS_UNAVAILABLE') => sprintf(
                'Validation service for %s is temporarily unavailable',
                $vatNumber->getCountryCode()
            ),
            str_contains($faultString, 'TIMEOUT') => 'Validation request timed out',
            str_contains($faultString, 'MS_MAX_CONCURRENT_REQ') => 'Service is busy, please try again later',
            default => sprintf('Validation service error: %s', $faultString),
        };

        // Return service unavailable for transient errors, invalid for permanent errors
        if (str_contains($faultString, 'INVALID_INPUT')) {
            return VatValidationResult::invalid($vatNumber, $errorMessage);
        }

        return VatValidationResult::serviceUnavailable($vatNumber, $errorMessage);
    }

    private function getSoapClient(): ?\SoapClient
    {
        if (null !== $this->soapClient) {
            return $this->soapClient;
        }

        try {
            $this->soapClient = new \SoapClient(self::VIES_WSDL, [
                'connection_timeout' => self::SOAP_TIMEOUT,
                'cache_wsdl' => WSDL_CACHE_MEMORY,
                'exceptions' => true,
                'trace' => true,
                'user_agent' => 'PHP VIES VAT Validator/1.0',
            ]);

            $this->logger->debug('VIES: SOAP client initialized successfully');

            return $this->soapClient;
        } catch (\Throwable $e) {
            $this->logger->error('VIES: Failed to initialize SOAP client', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return null;
        }
    }

    private function getCacheKey(VatNumber $vatNumber): string
    {
        return self::CACHE_KEY_PREFIX.strtolower($vatNumber->getFull());
    }
}
