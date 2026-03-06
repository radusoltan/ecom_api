<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Infrastructure\Command;

use App\Internationalization\Infrastructure\Cache\TranslationCacheService;
use App\Internationalization\Infrastructure\Command\WarmTranslationCacheCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(WarmTranslationCacheCommand::class)]
#[Group('unit')]
#[Group('infrastructure')]
final class WarmTranslationCacheCommandTest extends TestCase
{
    private TranslationCacheService&MockObject $cacheService;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->cacheService = $this->createMock(TranslationCacheService::class);

        $command = new WarmTranslationCacheCommand($this->cacheService);
        $this->tester = new CommandTester($command);
    }

    // -----------------------------------------------------------------------
    // Argument/option validation
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFailureWhenNeitherTenantIdNorAllProvided(): void
    {
        $this->cacheService->expects(self::never())->method('warmCache');

        $exit = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('You must provide either a tenantId or use --all option', $this->tester->getDisplay());
    }

    #[Test]
    public function itReturnsFailureWhenBothTenantIdAndAllProvided(): void
    {
        $this->cacheService->expects(self::never())->method('warmCache');

        $exit = $this->tester->execute([
            'tenantId' => '00000000-0000-4000-8000-000000000001',
            '--all' => true,
        ]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('cannot use both', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Single tenant by argument
    // -----------------------------------------------------------------------

    #[Test]
    public function itWarmsCacheForSpecificTenant(): void
    {
        $this->cacheService
            ->expects(self::once())
            ->method('warmCache')
            ->willReturn(20);

        $exit = $this->tester->execute(['tenantId' => '00000000-0000-4000-8000-000000000001']);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $this->tester->getDisplay();
        self::assertStringContainsString('20 locale/domain combinations', $display);
        self::assertStringContainsString('20 total combinations warmed', $display);
    }

    #[Test]
    public function itWarmsCacheWithZeroCombinations(): void
    {
        $this->cacheService
            ->expects(self::once())
            ->method('warmCache')
            ->willReturn(0);

        $exit = $this->tester->execute(['tenantId' => '00000000-0000-4000-8000-000000000001']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('0 total combinations warmed', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // --all option
    // -----------------------------------------------------------------------

    #[Test]
    public function itWarmsCacheForAllTenantsViaEnvVar(): void
    {
        $savedEnv = $_ENV['DEFAULT_TENANT_ID'] ?? null;
        $_ENV['DEFAULT_TENANT_ID'] = '00000000-0000-4000-8000-000000000001';

        $this->cacheService
            ->expects(self::once())
            ->method('warmCache')
            ->willReturn(15);

        $exit = $this->tester->execute(['--all' => true]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('15 total combinations warmed', $this->tester->getDisplay());

        if (null !== $savedEnv) {
            $_ENV['DEFAULT_TENANT_ID'] = $savedEnv;
        } else {
            unset($_ENV['DEFAULT_TENANT_ID']);
        }
    }

    #[Test]
    public function itReturnsFailureWhenAllOptionButNoEnvVar(): void
    {
        $savedEnv = $_ENV['DEFAULT_TENANT_ID'] ?? null;
        unset($_ENV['DEFAULT_TENANT_ID']);

        $this->cacheService->expects(self::never())->method('warmCache');

        $exit = $this->tester->execute(['--all' => true]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('No tenants found', $this->tester->getDisplay());

        if (null !== $savedEnv) {
            $_ENV['DEFAULT_TENANT_ID'] = $savedEnv;
        }
    }

    // -----------------------------------------------------------------------
    // --clear option
    // -----------------------------------------------------------------------

    #[Test]
    public function itClearsCacheBeforeWarming(): void
    {
        $this->cacheService->expects(self::once())->method('clearCache');
        $this->cacheService->expects(self::once())->method('warmCache')->willReturn(10);

        $exit = $this->tester->execute([
            'tenantId' => '00000000-0000-4000-8000-000000000001',
            '--clear' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Clearing existing cache', $this->tester->getDisplay());
        self::assertStringContainsString('Cache cleared', $this->tester->getDisplay());
    }

    #[Test]
    public function itDoesNotClearCacheWhenClearNotSet(): void
    {
        $this->cacheService->expects(self::never())->method('clearCache');
        $this->cacheService->expects(self::once())->method('warmCache')->willReturn(5);

        $exit = $this->tester->execute(['tenantId' => '00000000-0000-4000-8000-000000000001']);

        self::assertSame(Command::SUCCESS, $exit);
    }

    // -----------------------------------------------------------------------
    // Invalid tenant ID
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFailureForInvalidTenantId(): void
    {
        $this->cacheService->expects(self::never())->method('warmCache');

        $exit = $this->tester->execute(['tenantId' => 'not-a-valid-uuid']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Failed to warm cache for tenant', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Cache service throws exception
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFailureWhenWarmCacheThrows(): void
    {
        $this->cacheService
            ->expects(self::once())
            ->method('warmCache')
            ->willThrowException(new \Exception('Redis unavailable'));

        $exit = $this->tester->execute(['tenantId' => '00000000-0000-4000-8000-000000000001']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Failed to warm cache for tenant', $this->tester->getDisplay());
        self::assertStringContainsString('Redis unavailable', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Verbose output shows stack trace on exception
    // -----------------------------------------------------------------------

    #[Test]
    public function itShowsTraceInVerboseModeOnException(): void
    {
        $this->cacheService
            ->expects(self::once())
            ->method('warmCache')
            ->willThrowException(new \Exception('Cache error'));

        $exit = $this->tester->execute(
            ['tenantId' => '00000000-0000-4000-8000-000000000001'],
            ['verbosity' => \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE],
        );

        self::assertSame(Command::FAILURE, $exit);
    }

    // -----------------------------------------------------------------------
    // Duration shown in output
    // -----------------------------------------------------------------------

    #[Test]
    public function itShowsDurationInOutput(): void
    {
        $this->cacheService->method('warmCache')->willReturn(8);

        $exit = $this->tester->execute(['tenantId' => '00000000-0000-4000-8000-000000000001']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('ms', $this->tester->getDisplay());
    }
}
