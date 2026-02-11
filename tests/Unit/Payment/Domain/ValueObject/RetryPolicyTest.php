<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\ValueObject;

use App\Payment\Domain\ValueObject\RetryPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase
{
    // ========================================
    // Factory Method Tests
    // ========================================

    #[Test]
    public function defaultCreatesInstance(): void
    {
        $policy = RetryPolicy::default();

        $this->assertInstanceOf(RetryPolicy::class, $policy);
    }

    // ========================================
    // Max Attempts Tests
    // ========================================

    #[Test]
    public function maxAttemptsReturnsThree(): void
    {
        $policy = RetryPolicy::default();

        $this->assertSame(3, $policy->maxAttempts());
    }

    // ========================================
    // Delay Interval Tests
    // ========================================

    #[Test]
    public function getDelayForAttemptOneReturnsOneHour(): void
    {
        $policy = RetryPolicy::default();

        $delay = $policy->getDelayForAttempt(1);

        $this->assertSame(3600, $delay); // 1 hour in seconds
    }

    #[Test]
    public function getDelayForAttemptTwoReturnsFourHours(): void
    {
        $policy = RetryPolicy::default();

        $delay = $policy->getDelayForAttempt(2);

        $this->assertSame(14400, $delay); // 4 hours in seconds
    }

    #[Test]
    public function getDelayForAttemptThreeReturnsTwentyFourHours(): void
    {
        $policy = RetryPolicy::default();

        $delay = $policy->getDelayForAttempt(3);

        $this->assertSame(86400, $delay); // 24 hours in seconds
    }

    #[Test]
    public function getDelayForAttemptZeroThrowsException(): void
    {
        $policy = RetryPolicy::default();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid attempt number: 0. Must be between 1 and 3');

        $policy->getDelayForAttempt(0);
    }

    #[Test]
    public function getDelayForAttemptFourThrowsException(): void
    {
        $policy = RetryPolicy::default();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid attempt number: 4. Must be between 1 and 3');

        $policy->getDelayForAttempt(4);
    }

    #[Test]
    public function getDelayForAttemptNegativeThrowsException(): void
    {
        $policy = RetryPolicy::default();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid attempt number: -1. Must be between 1 and 3');

        $policy->getDelayForAttempt(-1);
    }

    // ========================================
    // Calculate Next Retry Time Tests
    // ========================================

    #[Test]
    public function calculateNextRetryTimeForAttemptZero(): void
    {
        $policy = RetryPolicy::default();
        $now = new \DateTimeImmutable('2025-01-01 12:00:00');

        $nextRetry = $policy->calculateNextRetryTime(0, $now);

        // First retry is after 1 hour (attempt 0 -> 1)
        $expected = new \DateTimeImmutable('2025-01-01 13:00:00');
        $this->assertEquals($expected, $nextRetry);
    }

    #[Test]
    public function calculateNextRetryTimeForAttemptOne(): void
    {
        $policy = RetryPolicy::default();
        $now = new \DateTimeImmutable('2025-01-01 12:00:00');

        $nextRetry = $policy->calculateNextRetryTime(1, $now);

        // Second retry is after 4 hours (attempt 1 -> 2)
        $expected = new \DateTimeImmutable('2025-01-01 16:00:00');
        $this->assertEquals($expected, $nextRetry);
    }

    #[Test]
    public function calculateNextRetryTimeForAttemptTwo(): void
    {
        $policy = RetryPolicy::default();
        $now = new \DateTimeImmutable('2025-01-01 12:00:00');

        $nextRetry = $policy->calculateNextRetryTime(2, $now);

        // Third retry is after 24 hours (attempt 2 -> 3)
        $expected = new \DateTimeImmutable('2025-01-02 12:00:00');
        $this->assertEquals($expected, $nextRetry);
    }

    #[Test]
    public function calculateNextRetryTimeThrowsExceptionAtMaxAttempts(): void
    {
        $policy = RetryPolicy::default();
        $now = new \DateTimeImmutable('2025-01-01 12:00:00');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot schedule retry. Max attempts (3) reached');

        $policy->calculateNextRetryTime(3, $now);
    }

    #[Test]
    public function calculateNextRetryTimeUsesProvidedNow(): void
    {
        $policy = RetryPolicy::default();
        $customNow = new \DateTimeImmutable('2025-06-15 14:30:00');

        $nextRetry = $policy->calculateNextRetryTime(0, $customNow);

        // First retry is 1 hour after custom now
        $expected = new \DateTimeImmutable('2025-06-15 15:30:00');
        $this->assertEquals($expected, $nextRetry);
    }

    #[Test]
    public function calculateNextRetryTimeUsesCurrentTimeWhenNowIsNull(): void
    {
        $policy = RetryPolicy::default();
        $before = new \DateTimeImmutable();

        $nextRetry = $policy->calculateNextRetryTime(0);

        $after = new \DateTimeImmutable();

        // Next retry should be roughly 1 hour from now
        $this->assertGreaterThan($before, $nextRetry);
        $this->assertLessThan($after->modify('+1 hour +1 minute'), $nextRetry);
    }

    // ========================================
    // Retryable Error Tests
    // ========================================

    #[Test]
    public function isRetryableForCardDeclined(): void
    {
        $policy = RetryPolicy::default();

        $this->assertTrue($policy->isRetryable('card_declined'));
    }

    #[Test]
    public function isRetryableForInsufficientFunds(): void
    {
        $policy = RetryPolicy::default();

        $this->assertTrue($policy->isRetryable('insufficient_funds'));
    }

    #[Test]
    public function isRetryableForProcessingError(): void
    {
        $policy = RetryPolicy::default();

        $this->assertTrue($policy->isRetryable('processing_error'));
    }

    #[Test]
    public function isRetryableForNetworkError(): void
    {
        $policy = RetryPolicy::default();

        $this->assertTrue($policy->isRetryable('network_error'));
    }

    #[Test]
    public function isRetryableForTimeout(): void
    {
        $policy = RetryPolicy::default();

        $this->assertTrue($policy->isRetryable('timeout'));
    }

    #[Test]
    public function isRetryableForGatewayTimeout(): void
    {
        $policy = RetryPolicy::default();

        $this->assertTrue($policy->isRetryable('gateway_timeout'));
    }

    #[Test]
    public function isRetryableForServiceUnavailable(): void
    {
        $policy = RetryPolicy::default();

        $this->assertTrue($policy->isRetryable('service_unavailable'));
    }

    #[Test]
    public function isRetryableForRateLimitExceeded(): void
    {
        $policy = RetryPolicy::default();

        $this->assertTrue($policy->isRetryable('rate_limit_exceeded'));
    }

    #[Test]
    public function isRetryableNormalizesErrorCodeWithSpaces(): void
    {
        $policy = RetryPolicy::default();

        // "Card Declined" should normalize to "card_declined"
        $this->assertTrue($policy->isRetryable('Card Declined'));
    }

    #[Test]
    public function isRetryableNormalizesErrorCodeWithUppercase(): void
    {
        $policy = RetryPolicy::default();

        // "INSUFFICIENT_FUNDS" should normalize to "insufficient_funds"
        $this->assertTrue($policy->isRetryable('INSUFFICIENT_FUNDS'));
    }

    #[Test]
    public function isRetryableNormalizesErrorCodeWithHyphens(): void
    {
        $policy = RetryPolicy::default();

        // "processing-error" should normalize to "processing_error"
        $this->assertTrue($policy->isRetryable('processing-error'));
    }

    #[Test]
    public function isRetryableNormalizesErrorCodeWithMixedFormat(): void
    {
        $policy = RetryPolicy::default();

        // "Network Error" should normalize to "network_error"
        $this->assertTrue($policy->isRetryable('Network Error'));
    }

    // ========================================
    // Non-Retryable Error Tests
    // ========================================

    #[Test]
    public function isNotRetryableForExpiredCard(): void
    {
        $policy = RetryPolicy::default();

        $this->assertFalse($policy->isRetryable('expired_card'));
    }

    #[Test]
    public function isNotRetryableForFraudulent(): void
    {
        $policy = RetryPolicy::default();

        $this->assertFalse($policy->isRetryable('fraudulent'));
    }

    #[Test]
    public function isNotRetryableForInvalidCardNumber(): void
    {
        $policy = RetryPolicy::default();

        $this->assertFalse($policy->isRetryable('invalid_card_number'));
    }

    #[Test]
    public function isNotRetryableForInvalidCvc(): void
    {
        $policy = RetryPolicy::default();

        $this->assertFalse($policy->isRetryable('invalid_cvc'));
    }

    #[Test]
    public function isNotRetryableForStolenCard(): void
    {
        $policy = RetryPolicy::default();

        $this->assertFalse($policy->isRetryable('stolen_card'));
    }

    #[Test]
    public function isNotRetryableForLostCard(): void
    {
        $policy = RetryPolicy::default();

        $this->assertFalse($policy->isRetryable('lost_card'));
    }

    #[Test]
    public function isNotRetryableForRestrictedCard(): void
    {
        $policy = RetryPolicy::default();

        $this->assertFalse($policy->isRetryable('restricted_card'));
    }

    #[Test]
    public function isNotRetryableForDoNotHonor(): void
    {
        $policy = RetryPolicy::default();

        $this->assertFalse($policy->isRetryable('do_not_honor'));
    }

    #[Test]
    public function isNotRetryableForInvalidAmount(): void
    {
        $policy = RetryPolicy::default();

        $this->assertFalse($policy->isRetryable('invalid_amount'));
    }

    #[Test]
    public function isNotRetryableForAuthenticationRequired(): void
    {
        $policy = RetryPolicy::default();

        $this->assertFalse($policy->isRetryable('authentication_required'));
    }

    #[Test]
    public function isNonRetryableReturnsTrueForExpiredCard(): void
    {
        $policy = RetryPolicy::default();

        $this->assertTrue($policy->isNonRetryable('expired_card'));
    }

    #[Test]
    public function isNonRetryableReturnsTrueForFraudulent(): void
    {
        $policy = RetryPolicy::default();

        $this->assertTrue($policy->isNonRetryable('fraudulent'));
    }

    #[Test]
    public function isNonRetryableReturnsTrueForStolenCard(): void
    {
        $policy = RetryPolicy::default();

        $this->assertTrue($policy->isNonRetryable('stolen_card'));
    }

    #[Test]
    public function isNonRetryableReturnsFalseForRetryableError(): void
    {
        $policy = RetryPolicy::default();

        $this->assertFalse($policy->isNonRetryable('card_declined'));
    }

    #[Test]
    public function isNonRetryableNormalizesErrorCode(): void
    {
        $policy = RetryPolicy::default();

        // "Expired Card" should normalize to "expired_card"
        $this->assertTrue($policy->isNonRetryable('Expired Card'));
    }

    // ========================================
    // Unknown Error Tests
    // ========================================

    #[Test]
    public function unknownErrorIsNotRetryable(): void
    {
        $policy = RetryPolicy::default();

        // Unknown error code should default to non-retryable (fail-safe)
        $this->assertFalse($policy->isRetryable('unknown_error_code'));
    }

    #[Test]
    public function unknownErrorIsNotExplicitlyNonRetryable(): void
    {
        $policy = RetryPolicy::default();

        // Unknown error is not in the explicit non-retryable list
        $this->assertFalse($policy->isNonRetryable('unknown_error_code'));
    }

    #[Test]
    public function emptyErrorCodeIsNotRetryable(): void
    {
        $policy = RetryPolicy::default();

        $this->assertFalse($policy->isRetryable(''));
    }

    // ========================================
    // Getter Tests
    // ========================================

    #[Test]
    public function getRetryableErrorsReturnsArray(): void
    {
        $policy = RetryPolicy::default();

        $errors = $policy->getRetryableErrors();

        $this->assertIsArray($errors);
        $this->assertCount(8, $errors);
        $this->assertContains('card_declined', $errors);
        $this->assertContains('insufficient_funds', $errors);
        $this->assertContains('processing_error', $errors);
        $this->assertContains('network_error', $errors);
        $this->assertContains('timeout', $errors);
        $this->assertContains('gateway_timeout', $errors);
        $this->assertContains('service_unavailable', $errors);
        $this->assertContains('rate_limit_exceeded', $errors);
    }

    #[Test]
    public function getNonRetryableErrorsReturnsArray(): void
    {
        $policy = RetryPolicy::default();

        $errors = $policy->getNonRetryableErrors();

        $this->assertIsArray($errors);
        $this->assertCount(10, $errors);
        $this->assertContains('expired_card', $errors);
        $this->assertContains('fraudulent', $errors);
        $this->assertContains('invalid_card_number', $errors);
        $this->assertContains('invalid_cvc', $errors);
        $this->assertContains('stolen_card', $errors);
        $this->assertContains('lost_card', $errors);
        $this->assertContains('restricted_card', $errors);
        $this->assertContains('do_not_honor', $errors);
        $this->assertContains('invalid_amount', $errors);
        $this->assertContains('authentication_required', $errors);
    }

    #[Test]
    public function getRetryScheduleDescriptionReturnsFormattedArray(): void
    {
        $policy = RetryPolicy::default();

        $schedule = $policy->getRetryScheduleDescription();

        $this->assertIsArray($schedule);
        $this->assertCount(3, $schedule);
        $this->assertArrayHasKey(1, $schedule);
        $this->assertArrayHasKey(2, $schedule);
        $this->assertArrayHasKey(3, $schedule);
        $this->assertSame('1 hour', $schedule[1]);
        $this->assertSame('4 hours', $schedule[2]);
        $this->assertSame('24 hours', $schedule[3]);
    }

    #[Test]
    public function getRetryScheduleDescriptionIsIndexedByAttemptNumber(): void
    {
        $policy = RetryPolicy::default();

        $schedule = $policy->getRetryScheduleDescription();

        // Verify the array is indexed by attempt number (1-based), not 0-based
        $this->assertArrayNotHasKey(0, $schedule);
        $this->assertArrayHasKey(1, $schedule);
        $this->assertArrayHasKey(2, $schedule);
        $this->assertArrayHasKey(3, $schedule);
    }

    // ========================================
    // Edge Case Tests
    // ========================================

    #[Test]
    public function normalizationHandlesMultipleSpaces(): void
    {
        $policy = RetryPolicy::default();

        // "Card   Declined" should normalize to "card_declined"
        $this->assertTrue($policy->isRetryable('Card   Declined'));
    }

    #[Test]
    public function normalizationHandlesLeadingAndTrailingSpaces(): void
    {
        $policy = RetryPolicy::default();

        // "  card_declined  " should normalize to "card_declined"
        $this->assertTrue($policy->isRetryable('  card_declined  '));
    }

    #[Test]
    public function normalizationHandlesMixedDelimiters(): void
    {
        $policy = RetryPolicy::default();

        // "Card-Declined" should normalize to "card_declined"
        $this->assertTrue($policy->isRetryable('Card-Declined'));
    }

    #[Test]
    public function calculateNextRetryTimeThrowsExceptionForAttemptBeyondMax(): void
    {
        $policy = RetryPolicy::default();
        $now = new \DateTimeImmutable('2025-01-01 12:00:00');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot schedule retry. Max attempts (3) reached');

        $policy->calculateNextRetryTime(4, $now);
    }

    #[Test]
    public function retryableAndNonRetryableListsDoNotOverlap(): void
    {
        $policy = RetryPolicy::default();

        $retryable = $policy->getRetryableErrors();
        $nonRetryable = $policy->getNonRetryableErrors();

        $overlap = array_intersect($retryable, $nonRetryable);

        $this->assertEmpty($overlap, 'Retryable and non-retryable error lists should not overlap');
    }
}
