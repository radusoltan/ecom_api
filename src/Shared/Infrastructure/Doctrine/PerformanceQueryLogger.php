<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Application\Service\PerformanceProfiler;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

/**
 * Doctrine DBAL Middleware for Performance Profiling.
 *
 * Replaces the deprecated SQLLogger to track:
 * - Query execution times
 * - Slow query detection
 * - Query count per request
 *
 * Business Rules (PRD Section 9.1):
 * - Query time < 100ms target
 * - Slow query logging threshold: 100ms
 * - Performance monitoring enabled in all environments
 */
final class PerformanceQueryLogger implements MiddlewareInterface
{
    public function __construct(
        private readonly PerformanceProfiler $profiler,
    ) {
    }

    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new class($driver, $this->profiler) extends AbstractDriverMiddleware {
            public function __construct(
                DriverInterface $driver,
                private readonly PerformanceProfiler $profiler,
            ) {
                parent::__construct($driver);
            }

            public function connect(array $params): ConnectionInterface
            {
                return new class(parent::connect($params), $this->profiler) extends AbstractConnectionMiddleware {
                    public function __construct(
                        ConnectionInterface $connection,
                        private readonly PerformanceProfiler $profiler,
                    ) {
                        parent::__construct($connection);
                    }

                    public function prepare(string $sql): Statement
                    {
                        return new class(parent::prepare($sql), $this->profiler, $sql) extends AbstractStatementMiddleware {
                            public function __construct(
                                Statement $statement,
                                private readonly PerformanceProfiler $profiler,
                                private readonly string $sql,
                            ) {
                                parent::__construct($statement);
                            }

                            public function execute(): Result
                            {
                                $startTime = microtime(true);
                                $result = parent::execute();
                                $duration = (microtime(true) - $startTime) * 1000;

                                $this->profiler->logQuery($this->sql, $duration);

                                return $result;
                            }
                        };
                    }

                    public function query(string $sql): Result
                    {
                        $startTime = microtime(true);
                        $result = parent::query($sql);
                        $duration = (microtime(true) - $startTime) * 1000;

                        $this->profiler->logQuery($sql, $duration);

                        return $result;
                    }

                    public function exec(string $sql): int|string
                    {
                        $startTime = microtime(true);
                        $result = parent::exec($sql);
                        $duration = (microtime(true) - $startTime) * 1000;

                        $this->profiler->logQuery($sql, $duration);

                        return $result;
                    }
                };
            }
        };
    }
}
