<?php

declare(strict_types=1);

namespace App\Order\Domain\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * FraudCheckService.
 *
 * Implements velocity-based fraud detection for order placement.
 * Uses IP and email tracking to identify suspicious ordering patterns.
 *
 * Business Rules:
 * - High Risk: >5 orders in 10 minutes from same IP
 * - High Risk: >3 orders in 10 minutes from same email
 * - Medium Risk: >3 orders in 10 minutes from same IP
 * - Medium Risk: >2 orders in 10 minutes from same email
 * - Suspicious patterns: multiple failed attempts + success
 *
 * Fraud Score: 0-100
 * - 0-30: Low risk (green)
 * - 31-60: Medium risk (yellow)
 * - 61-100: High risk (red) - manual review required
 *
 * @see PRD Section 8.1: Security Requirements
 */
final class FraudCheckService
{
    private const CACHE_PREFIX = 'fraud_check';
    private const TRACKING_WINDOW = 600; // 10 minutes
    private const IP_THRESHOLD_HIGH = 5;
    private const IP_THRESHOLD_MEDIUM = 3;
    private const EMAIL_THRESHOLD_HIGH = 3;
    private const EMAIL_THRESHOLD_MEDIUM = 2;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Calculate fraud score for an order placement attempt.
     *
     * @param string $ipAddress Client IP address
     * @param string $email     Customer email
     * @param string $tenantId  Tenant identifier
     *
     * @return array{score: int, risk_level: string, reasons: array<string>}
     */
    public function calculateFraudScore(string $ipAddress, string $email, string $tenantId): array
    {
        $score = 0;
        $reasons = [];

        // Check IP velocity
        $ipVelocity = $this->getVelocity('ip', $ipAddress, $tenantId);
        if ($ipVelocity >= self::IP_THRESHOLD_HIGH) {
            $score += 40;
            $reasons[] = sprintf('High order velocity from IP: %d orders in 10 minutes', $ipVelocity);
        } elseif ($ipVelocity >= self::IP_THRESHOLD_MEDIUM) {
            $score += 20;
            $reasons[] = sprintf('Medium order velocity from IP: %d orders in 10 minutes', $ipVelocity);
        }

        // Check email velocity
        $emailVelocity = $this->getVelocity('email', $email, $tenantId);
        if ($emailVelocity >= self::EMAIL_THRESHOLD_HIGH) {
            $score += 40;
            $reasons[] = sprintf('High order velocity from email: %d orders in 10 minutes', $emailVelocity);
        } elseif ($emailVelocity >= self::EMAIL_THRESHOLD_MEDIUM) {
            $score += 20;
            $reasons[] = sprintf('Medium order velocity from email: %d orders in 10 minutes', $emailVelocity);
        }

        // Check failed attempts
        $failedAttempts = $this->getFailedAttempts($ipAddress, $tenantId);
        if ($failedAttempts > 3) {
            $score += 20;
            $reasons[] = sprintf('Multiple failed payment attempts: %d failures', $failedAttempts);
        }

        // Determine risk level
        $riskLevel = $this->determineRiskLevel($score);

        if ($score > 30) {
            $this->logger->warning('Fraud check detected suspicious activity', [
                'score' => $score,
                'risk_level' => $riskLevel,
                'ip' => $ipAddress,
                'email' => $email,
                'tenant_id' => $tenantId,
                'reasons' => $reasons,
            ]);
        }

        return [
            'score' => min(100, $score),
            'risk_level' => $riskLevel,
            'reasons' => $reasons,
        ];
    }

    /**
     * Record a successful order placement.
     */
    public function recordOrderPlaced(string $ipAddress, string $email, string $tenantId): void
    {
        $this->incrementVelocity('ip', $ipAddress, $tenantId);
        $this->incrementVelocity('email', $email, $tenantId);

        $this->logger->info('Fraud check: order placed recorded', [
            'ip' => $ipAddress,
            'email' => $email,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Record a failed order attempt (e.g., payment failure).
     */
    public function recordFailedAttempt(string $ipAddress, string $tenantId, string $reason): void
    {
        $cacheKey = $this->buildCacheKey('failed', $ipAddress, $tenantId);

        try {
            $this->cache->get($cacheKey, function (ItemInterface $item) {
                $item->expiresAfter(self::TRACKING_WINDOW);

                return 1;
            });

            // Increment counter
            $count = (int) $this->cache->get($cacheKey, fn () => 0);
            $this->cache->delete($cacheKey);
            $this->cache->get($cacheKey, function (ItemInterface $item) use ($count) {
                $item->expiresAfter(self::TRACKING_WINDOW);

                return $count + 1;
            });

            $this->logger->info('Fraud check: failed attempt recorded', [
                'ip' => $ipAddress,
                'tenant_id' => $tenantId,
                'reason' => $reason,
                'count' => $count + 1,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to record failed attempt', [
                'error' => $e->getMessage(),
                'ip' => $ipAddress,
            ]);
        }
    }

    /**
     * Get velocity count for a specific identifier.
     */
    private function getVelocity(string $type, string $identifier, string $tenantId): int
    {
        $cacheKey = $this->buildCacheKey($type, $identifier, $tenantId);

        try {
            return (int) $this->cache->get($cacheKey, fn () => 0);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get velocity', [
                'error' => $e->getMessage(),
                'type' => $type,
            ]);

            return 0;
        }
    }

    /**
     * Increment velocity counter.
     */
    private function incrementVelocity(string $type, string $identifier, string $tenantId): void
    {
        $cacheKey = $this->buildCacheKey($type, $identifier, $tenantId);

        try {
            $count = $this->getVelocity($type, $identifier, $tenantId);
            $this->cache->delete($cacheKey);
            $this->cache->get($cacheKey, function (ItemInterface $item) use ($count) {
                $item->expiresAfter(self::TRACKING_WINDOW);

                return $count + 1;
            });
        } catch (\Exception $e) {
            $this->logger->error('Failed to increment velocity', [
                'error' => $e->getMessage(),
                'type' => $type,
            ]);
        }
    }

    /**
     * Get failed attempts count for an IP.
     */
    private function getFailedAttempts(string $ipAddress, string $tenantId): int
    {
        $cacheKey = $this->buildCacheKey('failed', $ipAddress, $tenantId);

        try {
            return (int) $this->cache->get($cacheKey, fn () => 0);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get failed attempts', [
                'error' => $e->getMessage(),
                'ip' => $ipAddress,
            ]);

            return 0;
        }
    }

    /**
     * Determine risk level based on score.
     */
    private function determineRiskLevel(int $score): string
    {
        return match (true) {
            $score >= 61 => 'high',
            $score >= 31 => 'medium',
            default => 'low',
        };
    }

    /**
     * Build cache key for tracking.
     */
    private function buildCacheKey(string $type, string $identifier, string $tenantId): string
    {
        return sprintf(
            '%s:%s:%s:%s',
            self::CACHE_PREFIX,
            $tenantId,
            $type,
            hash('sha256', $identifier)
        );
    }
}
