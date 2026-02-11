<?php

declare(strict_types=1);

namespace App\Payment\Domain\ValueObject;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Payment Retry Policy Value Object.
 *
 * Defines retry strategy for failed payments:
 * - Maximum retry attempts
 * - Exponential backoff delay intervals
 * - Retryable vs non-retryable error codes
 *
 * Business Rules:
 * - max_attempts: 3
 * - delay_intervals: [1 hour, 4 hours, 24 hours]
 * - retryable_errors: card_declined, insufficient_funds, processing_error, network_error, timeout
 * - non_retryable_errors: expired_card, fraudulent, invalid_card_number, invalid_cvc, stolen_card
 */
#[Exclude]
final readonly class RetryPolicy
{
    private const MAX_ATTEMPTS = 3;

    // Delay intervals in seconds
    private const DELAY_INTERVALS = [
        3600,    // 1 hour
        14400,   // 4 hours
        86400,   // 24 hours
    ];

    // Error codes that should trigger retry
    private const RETRYABLE_ERRORS = [
        'card_declined',
        'insufficient_funds',
        'processing_error',
        'network_error',
        'timeout',
        'gateway_timeout',
        'service_unavailable',
        'rate_limit_exceeded',
    ];

    // Error codes that should NOT be retried
    private const NON_RETRYABLE_ERRORS = [
        'expired_card',
        'fraudulent',
        'invalid_card_number',
        'invalid_cvc',
        'stolen_card',
        'lost_card',
        'restricted_card',
        'do_not_honor',
        'invalid_amount',
        'authentication_required',
    ];

    private function __construct()
    {
    }

    public static function default(): self
    {
        return new self();
    }

    /**
     * Get maximum number of retry attempts allowed.
     */
    public function maxAttempts(): int
    {
        return self::MAX_ATTEMPTS;
    }

    /**
     * Get delay in seconds for a specific retry attempt.
     *
     * @param int $attemptNumber Current attempt number (1-indexed)
     *
     * @return int Delay in seconds
     *
     * @throws \InvalidArgumentException if attempt number exceeds max attempts
     */
    public function getDelayForAttempt(int $attemptNumber): int
    {
        if ($attemptNumber < 1 || $attemptNumber > self::MAX_ATTEMPTS) {
            throw new \InvalidArgumentException(sprintf('Invalid attempt number: %d. Must be between 1 and %d', $attemptNumber, self::MAX_ATTEMPTS));
        }

        // Array is 0-indexed, but attempt numbers are 1-indexed
        $index = $attemptNumber - 1;

        return self::DELAY_INTERVALS[$index];
    }

    /**
     * Calculate next retry time based on current attempt.
     *
     * @param int                     $currentAttempt Current retry attempt number (0-indexed)
     * @param \DateTimeImmutable|null $now            Current time (for testing)
     *
     * @return \DateTimeImmutable Next retry time
     */
    public function calculateNextRetryTime(int $currentAttempt, ?\DateTimeImmutable $now = null): \DateTimeImmutable
    {
        $now = $now ?? new \DateTimeImmutable();

        if ($currentAttempt >= self::MAX_ATTEMPTS) {
            throw new \InvalidArgumentException(sprintf('Cannot schedule retry. Max attempts (%d) reached', self::MAX_ATTEMPTS));
        }

        // Next attempt is currentAttempt + 1 (convert from 0-indexed to 1-indexed)
        $nextAttemptNumber = $currentAttempt + 1;
        $delay = $this->getDelayForAttempt($nextAttemptNumber);

        return $now->modify(sprintf('+%d seconds', $delay));
    }

    /**
     * Determine if an error should trigger a retry.
     *
     * @param string $errorCode Error code from payment gateway
     *
     * @return bool True if error is retryable
     */
    public function isRetryable(string $errorCode): bool
    {
        $normalizedCode = $this->normalizeErrorCode($errorCode);

        // First check non-retryable list (explicit no-retry)
        if (in_array($normalizedCode, self::NON_RETRYABLE_ERRORS, true)) {
            return false;
        }

        // Then check retryable list (explicit retry)
        if (in_array($normalizedCode, self::RETRYABLE_ERRORS, true)) {
            return true;
        }

        // Default to non-retryable for unknown errors (fail-safe)
        return false;
    }

    /**
     * Determine if error is explicitly non-retryable.
     */
    public function isNonRetryable(string $errorCode): bool
    {
        $normalizedCode = $this->normalizeErrorCode($errorCode);

        return in_array($normalizedCode, self::NON_RETRYABLE_ERRORS, true);
    }

    /**
     * Get all retryable error codes.
     *
     * @return array<string>
     */
    public function getRetryableErrors(): array
    {
        return self::RETRYABLE_ERRORS;
    }

    /**
     * Get all non-retryable error codes.
     *
     * @return array<string>
     */
    public function getNonRetryableErrors(): array
    {
        return self::NON_RETRYABLE_ERRORS;
    }

    /**
     * Normalize error code to lowercase with underscores.
     *
     * Handles variations like:
     * - "Card Declined" -> "card_declined"
     * - "INSUFFICIENT_FUNDS" -> "insufficient_funds"
     * - "processing-error" -> "processing_error"
     */
    private function normalizeErrorCode(string $errorCode): string
    {
        // Convert to lowercase
        $normalized = strtolower($errorCode);

        // Replace spaces and hyphens with underscores
        $normalized = str_replace([' ', '-'], '_', $normalized);

        // Remove multiple consecutive underscores
        $result = preg_replace('/_+/', '_', $normalized);
        $normalized = is_string($result) ? $result : $normalized;

        // Trim underscores from start and end
        return trim($normalized, '_');
    }

    /**
     * Get human-readable retry schedule.
     *
     * @return array<int, string> Array of delay descriptions indexed by attempt number
     */
    public function getRetryScheduleDescription(): array
    {
        $schedule = [];

        foreach (self::DELAY_INTERVALS as $index => $seconds) {
            $attemptNumber = $index + 1;
            $hours = $seconds / 3600;

            // @phpstan-ignore-next-line greaterOrEqual.alwaysTrue (all current intervals are >= 1 hour)
            if ($hours >= 1) {
                if (1 == $hours) {
                    $description = '1 hour';
                } else {
                    $description = sprintf('%d hours', $hours);
                }
            } else {
                $minutes = $seconds / 60;
                $description = sprintf('%d minutes', $minutes);
            }

            $schedule[$attemptNumber] = $description;
        }

        return $schedule;
    }
}
