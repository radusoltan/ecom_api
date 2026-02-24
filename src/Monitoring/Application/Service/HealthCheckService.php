<?php

declare(strict_types=1);

namespace App\Monitoring\Application\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Health check service verifying connectivity to all infrastructure dependencies.
 *
 * Returns RFC 8631 compliant health check responses with individual
 * component status for: Database, Redis, RabbitMQ, Elasticsearch.
 */
final readonly class HealthCheckService
{
    public function __construct(
        private Connection $connection,
        private \Redis|null $redis,
        private LoggerInterface $logger,
        private string $elasticsearchUrl = '',
        private string $rabbitmqUrl = '',
    ) {
    }

    /**
     * Run all health checks and return aggregated status.
     *
     * @return array{status: string, checks: array<string, array{status: string, time: string, output: string}>}
     */
    public function check(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'rabbitmq' => $this->checkRabbitMq(),
            'elasticsearch' => $this->checkElasticsearch(),
        ];

        $overallStatus = 'pass';
        foreach ($checks as $check) {
            if ('fail' === $check['status']) {
                $overallStatus = 'fail';
                break;
            }
            if ('warn' === $check['status']) {
                $overallStatus = 'warn';
            }
        }

        return [
            'status' => $overallStatus,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{status: string, time: string, output: string}
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            $this->connection->executeQuery('SELECT 1');
            $duration = (microtime(true) - $start) * 1000;

            return [
                'status' => $duration > 100 ? 'warn' : 'pass',
                'time' => date('c'),
                'output' => sprintf('Connected (%.1fms)', $duration),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Database health check failed', ['error' => $e->getMessage()]);

            return [
                'status' => 'fail',
                'time' => date('c'),
                'output' => 'Connection failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, time: string, output: string}
     */
    private function checkRedis(): array
    {
        if (null === $this->redis) {
            return [
                'status' => 'warn',
                'time' => date('c'),
                'output' => 'Redis not configured',
            ];
        }

        try {
            $start = microtime(true);
            $pong = $this->redis->ping();
            $duration = (microtime(true) - $start) * 1000;

            if (true === $pong || '+PONG' === $pong) {
                return [
                    'status' => $duration > 50 ? 'warn' : 'pass',
                    'time' => date('c'),
                    'output' => sprintf('Connected (%.1fms)', $duration),
                ];
            }

            return [
                'status' => 'fail',
                'time' => date('c'),
                'output' => 'Unexpected ping response',
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Redis health check failed', ['error' => $e->getMessage()]);

            return [
                'status' => 'fail',
                'time' => date('c'),
                'output' => 'Connection failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, time: string, output: string}
     */
    private function checkRabbitMq(): array
    {
        if ('' === $this->rabbitmqUrl) {
            return [
                'status' => 'warn',
                'time' => date('c'),
                'output' => 'RabbitMQ not configured',
            ];
        }

        try {
            $parts = parse_url($this->rabbitmqUrl);
            if (false === $parts || !isset($parts['host'])) {
                return [
                    'status' => 'fail',
                    'time' => date('c'),
                    'output' => 'Invalid RabbitMQ URL',
                ];
            }

            $start = microtime(true);
            $port = $parts['port'] ?? 5672;
            $socket = @fsockopen($parts['host'], (int) $port, $errno, $errstr, 3);
            $duration = (microtime(true) - $start) * 1000;

            if (false === $socket) {
                return [
                    'status' => 'fail',
                    'time' => date('c'),
                    'output' => sprintf('Connection refused: %s', $errstr),
                ];
            }

            fclose($socket);

            return [
                'status' => $duration > 100 ? 'warn' : 'pass',
                'time' => date('c'),
                'output' => sprintf('Port reachable (%.1fms)', $duration),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('RabbitMQ health check failed', ['error' => $e->getMessage()]);

            return [
                'status' => 'fail',
                'time' => date('c'),
                'output' => 'Check failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, time: string, output: string}
     */
    private function checkElasticsearch(): array
    {
        if ('' === $this->elasticsearchUrl) {
            return [
                'status' => 'warn',
                'time' => date('c'),
                'output' => 'Elasticsearch not configured',
            ];
        }

        try {
            $start = microtime(true);
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'method' => 'GET',
                ],
            ]);
            $response = @file_get_contents($this->elasticsearchUrl.'/_cluster/health', false, $context);
            $duration = (microtime(true) - $start) * 1000;

            if (false === $response) {
                return [
                    'status' => 'fail',
                    'time' => date('c'),
                    'output' => 'Connection failed',
                ];
            }

            $health = json_decode($response, true);
            $clusterStatus = $health['status'] ?? 'unknown';

            $status = match ($clusterStatus) {
                'green' => 'pass',
                'yellow' => 'warn',
                default => 'fail',
            };

            return [
                'status' => $status,
                'time' => date('c'),
                'output' => sprintf('Cluster %s (%.1fms)', $clusterStatus, $duration),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Elasticsearch health check failed', ['error' => $e->getMessage()]);

            return [
                'status' => 'fail',
                'time' => date('c'),
                'output' => 'Check failed: '.$e->getMessage(),
            ];
        }
    }
}
