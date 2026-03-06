<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Application\Command;

use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Tenant\Application\Command\DisableLocaleCommand;
use App\Tenant\Application\Command\DisableLocaleCommandHandler;
use App\Tenant\Application\Command\EnableLocaleCommand;
use App\Tenant\Application\Command\EnableLocaleCommandHandler;
use App\Tenant\Application\Command\SetDefaultLocaleCommand;
use App\Tenant\Application\Command\SetDefaultLocaleCommandHandler;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(EnableLocaleCommandHandler::class)]
#[CoversClass(DisableLocaleCommandHandler::class)]
#[CoversClass(SetDefaultLocaleCommandHandler::class)]
final class LocaleCommandHandlersTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private TenantRepositoryInterface&MockObject $repository;
    private LoggerInterface&MockObject $logger;
    private PerformanceProfiler $profiler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TenantRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->profiler = new PerformanceProfiler($this->logger);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeTenant(): Tenant
    {
        $tenant = Tenant::create(
            TenantName::fromString('Acme Corp'),
            Email::fromString('owner@acme.com'),
        );
        $tenant->popEvents();

        return $tenant;
    }

    // -----------------------------------------------------------------------
    // EnableLocaleCommandHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function itEnablesLocaleWhenTenantExists(): void
    {
        $tenant = $this->makeTenant();

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($tenant);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with($tenant);

        $handler = new EnableLocaleCommandHandler(
            $this->repository,
            $this->profiler,
            $this->logger,
        );

        // 'fr' is not enabled by default (only 'en' is)
        ($handler)(new EnableLocaleCommand(self::TENANT_ID, 'fr'));

        // Verify 'fr' was added
        $localeValues = array_map(
            fn (LanguageCode $l) => $l->value(),
            $tenant->enabledLocales(),
        );
        self::assertContains('fr', $localeValues);
    }

    #[Test]
    public function itThrowsTenantNotFoundWhenEnablingLocaleForMissingTenant(): void
    {
        $this->repository->method('findById')->willReturn(null);
        $this->repository->expects(self::never())->method('save');

        $handler = new EnableLocaleCommandHandler(
            $this->repository,
            $this->profiler,
            $this->logger,
        );

        $this->expectException(TenantNotFoundException::class);

        ($handler)(new EnableLocaleCommand(self::TENANT_ID, 'fr'));
    }

    #[Test]
    public function itRethrowsRepositoryExceptionOnEnableLocale(): void
    {
        $this->repository->method('findById')->willReturn($this->makeTenant());
        $this->repository->method('save')->willThrowException(new \RuntimeException('DB error'));

        $handler = new EnableLocaleCommandHandler(
            $this->repository,
            $this->profiler,
            $this->logger,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        ($handler)(new EnableLocaleCommand(self::TENANT_ID, 'fr'));
    }

    // -----------------------------------------------------------------------
    // DisableLocaleCommandHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function itDisablesLocaleWhenTenantExists(): void
    {
        $tenant = $this->makeTenant();
        // First enable 'fr' so it can be disabled
        $tenant->enableLocale(LanguageCode::fromString('fr'));
        $tenant->popEvents();

        $this->repository->method('findById')->willReturn($tenant);
        $this->repository->expects(self::once())->method('save')->with($tenant);

        $handler = new DisableLocaleCommandHandler(
            $this->repository,
            $this->profiler,
            $this->logger,
        );

        ($handler)(new DisableLocaleCommand(self::TENANT_ID, 'fr'));

        $localeValues = array_map(
            fn (LanguageCode $l) => $l->value(),
            $tenant->enabledLocales(),
        );
        self::assertNotContains('fr', $localeValues);
    }

    #[Test]
    public function itThrowsTenantNotFoundWhenDisablingLocaleForMissingTenant(): void
    {
        $this->repository->method('findById')->willReturn(null);
        $this->repository->expects(self::never())->method('save');

        $handler = new DisableLocaleCommandHandler(
            $this->repository,
            $this->profiler,
            $this->logger,
        );

        $this->expectException(TenantNotFoundException::class);

        ($handler)(new DisableLocaleCommand(self::TENANT_ID, 'fr'));
    }

    #[Test]
    public function itThrowsDomainExceptionWhenDisablingDefaultLocale(): void
    {
        // 'en' is the default locale — cannot disable it
        $this->repository->method('findById')->willReturn($this->makeTenant());
        $this->repository->expects(self::never())->method('save');

        $handler = new DisableLocaleCommandHandler(
            $this->repository,
            $this->profiler,
            $this->logger,
        );

        $this->expectException(\DomainException::class);

        ($handler)(new DisableLocaleCommand(self::TENANT_ID, 'en'));
    }

    // -----------------------------------------------------------------------
    // SetDefaultLocaleCommandHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function itSetsDefaultLocaleWhenLocaleIsEnabled(): void
    {
        $tenant = $this->makeTenant();
        $tenant->enableLocale(LanguageCode::fromString('fr'));
        $tenant->popEvents();

        $this->repository->method('findById')->willReturn($tenant);
        $this->repository->expects(self::once())->method('save')->with($tenant);

        $handler = new SetDefaultLocaleCommandHandler(
            $this->repository,
            $this->profiler,
            $this->logger,
        );

        ($handler)(new SetDefaultLocaleCommand(self::TENANT_ID, 'fr'));

        self::assertSame('fr', $tenant->defaultLocale()->value());
    }

    #[Test]
    public function itThrowsTenantNotFoundWhenSettingDefaultLocaleForMissingTenant(): void
    {
        $this->repository->method('findById')->willReturn(null);
        $this->repository->expects(self::never())->method('save');

        $handler = new SetDefaultLocaleCommandHandler(
            $this->repository,
            $this->profiler,
            $this->logger,
        );

        $this->expectException(TenantNotFoundException::class);

        ($handler)(new SetDefaultLocaleCommand(self::TENANT_ID, 'fr'));
    }

    #[Test]
    public function itThrowsDomainExceptionWhenSettingDefaultToDisabledLocale(): void
    {
        // 'de' is not enabled — cannot set as default
        $this->repository->method('findById')->willReturn($this->makeTenant());
        $this->repository->expects(self::never())->method('save');

        $handler = new SetDefaultLocaleCommandHandler(
            $this->repository,
            $this->profiler,
            $this->logger,
        );

        $this->expectException(\DomainException::class);

        ($handler)(new SetDefaultLocaleCommand(self::TENANT_ID, 'de'));
    }

    #[Test]
    public function itRethrowsRepositoryExceptionOnSetDefaultLocale(): void
    {
        $tenant = $this->makeTenant();
        // 'en' is already default and enabled — setting to 'en' again is valid
        // But we need to test via another enabled locale first:
        $tenant->enableLocale(LanguageCode::fromString('fr'));
        $tenant->popEvents();

        $this->repository->method('findById')->willReturn($tenant);
        $this->repository->method('save')->willThrowException(new \RuntimeException('DB error'));

        $handler = new SetDefaultLocaleCommandHandler(
            $this->repository,
            $this->profiler,
            $this->logger,
        );

        $this->expectException(\RuntimeException::class);

        ($handler)(new SetDefaultLocaleCommand(self::TENANT_ID, 'fr'));
    }
}
