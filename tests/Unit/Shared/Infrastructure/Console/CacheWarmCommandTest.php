<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Console;

use App\Shared\Infrastructure\Cache\CacheWarmingService;
use App\Shared\Infrastructure\Console\CacheWarmCommand;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('shared')]
final class CacheWarmCommandTest extends TestCase
{
    private CacheWarmingService&MockObject $warmingService;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->warmingService = $this->createMock(CacheWarmingService::class);
        $command = new CacheWarmCommand($this->warmingService);
        $this->tester = new CommandTester($command);
    }

    public function testWarmSpecificTenant(): void
    {
        $this->warmingService
            ->expects(self::once())
            ->method('warmTenant')
            ->willReturn(['translations' => 50, 'products' => 20, 'categories' => 5]);

        $this->tester->execute([
            'tenant-id' => '00000000-0000-4000-8000-000000000001',
            '--locale' => 'en_US',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('Cache warmed successfully', $this->tester->getDisplay());
    }

    public function testWarmAllTenants(): void
    {
        $this->warmingService
            ->expects(self::once())
            ->method('warmAllTenants')
            ->willReturn([
                ['translations' => 30, 'products' => 10, 'categories' => 3],
                ['translations' => 25, 'products' => 8, 'categories' => 2],
            ]);

        $this->tester->execute([]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('Cache warmed for 2 tenants', $this->tester->getDisplay());
    }

    public function testInvalidTenantId(): void
    {
        $this->tester->execute(['tenant-id' => 'not-a-uuid']);

        self::assertSame(1, $this->tester->getStatusCode());
    }

    public function testWarmingThrowsException(): void
    {
        $this->warmingService
            ->method('warmTenant')
            ->willThrowException(new \RuntimeException('Connection failed'));

        $this->tester->execute([
            'tenant-id' => '00000000-0000-4000-8000-000000000001',
        ]);

        self::assertSame(1, $this->tester->getStatusCode());
        self::assertStringContainsString('Cache warming failed', $this->tester->getDisplay());
    }

    public function testWarmTenantWithCustomLocale(): void
    {
        $this->warmingService
            ->method('warmTenant')
            ->willReturn(['translations' => 100, 'products' => 15, 'categories' => 8]);

        $this->tester->execute([
            'tenant-id' => '00000000-0000-4000-8000-000000000001',
            '--locale' => 'ro_RO',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('ro_RO', $this->tester->getDisplay());
    }
}
