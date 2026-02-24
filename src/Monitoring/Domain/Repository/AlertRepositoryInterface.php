<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Repository;

interface AlertRepositoryInterface
{
    /**
     * Save an active alert.
     *
     * @param array<string, mixed> $context
     */
    public function save(string $metric, string $severity, string $message, array $context = []): void;

    /**
     * Get all active (unresolved) alerts.
     *
     * @return list<array{metric: string, severity: string, message: string, timestamp: float, context: array<string, mixed>}>
     */
    public function getActive(): array;

    /**
     * Resolve (clear) an alert by metric name.
     */
    public function resolve(string $metric): void;

    /**
     * Clear all active alerts.
     */
    public function clearAll(): void;
}
