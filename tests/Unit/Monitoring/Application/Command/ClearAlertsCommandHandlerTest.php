<?php

declare(strict_types=1);

namespace App\Tests\Unit\Monitoring\Application\Command;

use App\Monitoring\Application\Command\ClearAlertsCommand;
use App\Monitoring\Application\Command\ClearAlertsCommandHandler;
use App\Monitoring\Infrastructure\Service\ApplicationPerformanceMonitor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClearAlertsCommandHandler::class)]
#[CoversClass(ClearAlertsCommand::class)]
final class ClearAlertsCommandHandlerTest extends TestCase
{
    #[Test]
    public function itClearsAlertsViaApm(): void
    {
        $apm = $this->createMock(ApplicationPerformanceMonitor::class);
        $apm->expects(self::once())->method('clearAlerts');

        $handler = new ClearAlertsCommandHandler($apm);
        $handler(new ClearAlertsCommand());
    }
}
