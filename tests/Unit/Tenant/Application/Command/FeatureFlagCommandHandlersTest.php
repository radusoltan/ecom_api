<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Application\Command\CreateFeatureFlagCommand;
use App\Tenant\Application\Command\CreateFeatureFlagCommandHandler;
use App\Tenant\Application\Command\DeleteFeatureFlagCommand;
use App\Tenant\Application\Command\DeleteFeatureFlagCommandHandler;
use App\Tenant\Application\Command\ToggleFeatureFlagCommand;
use App\Tenant\Application\Command\ToggleFeatureFlagCommandHandler;
use App\Tenant\Application\Command\UpdateFeatureFlagConfigCommand;
use App\Tenant\Application\Command\UpdateFeatureFlagConfigCommandHandler;
use App\Tenant\Application\Service\FeatureFlagService;
use App\Tenant\Domain\Model\FeatureFlag;
use App\Tenant\Domain\Model\FeatureFlagId;
use App\Tenant\Domain\Repository\FeatureFlagRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateFeatureFlagCommandHandler::class)]
#[CoversClass(ToggleFeatureFlagCommandHandler::class)]
#[CoversClass(UpdateFeatureFlagConfigCommandHandler::class)]
#[CoversClass(DeleteFeatureFlagCommandHandler::class)]
final class FeatureFlagCommandHandlersTest extends TestCase
{
    private FeatureFlagRepositoryInterface&MockObject $repository;
    private FeatureFlagService&MockObject $featureFlagService;
    private TenantId $tenantId;

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->repository = $this->createMock(FeatureFlagRepositoryInterface::class);
        $this->featureFlagService = $this->createMock(FeatureFlagService::class);
        $this->tenantId = TenantId::fromString(self::TENANT_ID);
    }

    private function createEnabledFlag(string $featureName = 'my_feature'): FeatureFlag
    {
        $flag = FeatureFlag::create(
            id: FeatureFlagId::generate(),
            tenantId: $this->tenantId,
            featureName: $featureName,
            enabled: true,
        );
        $flag->popEvents();

        return $flag;
    }

    private function createDisabledFlag(string $featureName = 'my_feature'): FeatureFlag
    {
        $flag = FeatureFlag::create(
            id: FeatureFlagId::generate(),
            tenantId: $this->tenantId,
            featureName: $featureName,
            enabled: false,
        );
        $flag->popEvents();

        return $flag;
    }

    // -----------------------------------------------------------------------
    // CreateFeatureFlagCommandHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function createHandlerCreatesNewFlag(): void
    {
        $handler = new CreateFeatureFlagCommandHandler($this->repository);
        $command = new CreateFeatureFlagCommand(
            tenantId: self::TENANT_ID,
            featureName: 'new_feature',
            enabled: true,
            configuration: ['limit' => 100],
            description: 'Test feature',
        );

        $this->repository->method('findByTenantAndFeature')->willReturn(null);
        $this->repository->expects(self::once())->method('save')
            ->with(self::isInstanceOf(FeatureFlag::class));

        $id = $handler($command);

        self::assertNotEmpty($id);
    }

    #[Test]
    public function createHandlerReturnsUuidString(): void
    {
        $handler = new CreateFeatureFlagCommandHandler($this->repository);
        $command = new CreateFeatureFlagCommand(
            tenantId: self::TENANT_ID,
            featureName: 'another_feature',
        );

        $this->repository->method('findByTenantAndFeature')->willReturn(null);
        $this->repository->method('save');

        $id = $handler($command);

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id
        );
    }

    #[Test]
    public function createHandlerThrowsWhenFlagAlreadyExists(): void
    {
        $handler = new CreateFeatureFlagCommandHandler($this->repository);
        $command = new CreateFeatureFlagCommand(
            tenantId: self::TENANT_ID,
            featureName: 'existing_feature',
        );

        $existingFlag = $this->createEnabledFlag('existing_feature');
        $this->repository->method('findByTenantAndFeature')->willReturn($existingFlag);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already exists');

        $handler($command);
    }

    #[Test]
    public function createHandlerCreatesDisabledFlagByDefault(): void
    {
        $handler = new CreateFeatureFlagCommandHandler($this->repository);
        $command = new CreateFeatureFlagCommand(
            tenantId: self::TENANT_ID,
            featureName: 'default_flag',
            // enabled defaults to false
        );

        $this->repository->method('findByTenantAndFeature')->willReturn(null);

        $capturedFlag = null;
        $this->repository->expects(self::once())->method('save')
            ->with(self::callback(function (FeatureFlag $f) use (&$capturedFlag): bool {
                $capturedFlag = $f;

                return true;
            }));

        $handler($command);

        self::assertNotNull($capturedFlag);
        self::assertFalse($capturedFlag->isEnabled());
    }

    // -----------------------------------------------------------------------
    // ToggleFeatureFlagCommandHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function toggleHandlerEnablesDisabledFlag(): void
    {
        $handler = new ToggleFeatureFlagCommandHandler($this->repository, $this->featureFlagService);
        $disabledFlag = $this->createDisabledFlag('dark_mode');
        $command = new ToggleFeatureFlagCommand(self::TENANT_ID, 'dark_mode', true);

        $this->repository->method('findByTenantAndFeature')->willReturn($disabledFlag);
        $this->repository->expects(self::once())->method('save');
        $this->featureFlagService->expects(self::once())->method('invalidateCache');

        $handler($command);

        self::assertTrue($disabledFlag->isEnabled());
    }

    #[Test]
    public function toggleHandlerDisablesEnabledFlag(): void
    {
        $handler = new ToggleFeatureFlagCommandHandler($this->repository, $this->featureFlagService);
        $enabledFlag = $this->createEnabledFlag('dark_mode');
        $command = new ToggleFeatureFlagCommand(self::TENANT_ID, 'dark_mode', false);

        $this->repository->method('findByTenantAndFeature')->willReturn($enabledFlag);
        $this->repository->expects(self::once())->method('save');
        $this->featureFlagService->expects(self::once())->method('invalidateCache');

        $handler($command);

        self::assertFalse($enabledFlag->isEnabled());
    }

    #[Test]
    public function toggleHandlerThrowsWhenFlagNotFound(): void
    {
        $handler = new ToggleFeatureFlagCommandHandler($this->repository, $this->featureFlagService);
        $command = new ToggleFeatureFlagCommand(self::TENANT_ID, 'nonexistent', true);

        $this->repository->method('findByTenantAndFeature')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        $handler($command);
    }

    #[Test]
    public function toggleHandlerInvalidatesCacheAfterSave(): void
    {
        $handler = new ToggleFeatureFlagCommandHandler($this->repository, $this->featureFlagService);
        $flag = $this->createDisabledFlag('my_flag');
        $command = new ToggleFeatureFlagCommand(self::TENANT_ID, 'my_flag', true);

        $this->repository->method('findByTenantAndFeature')->willReturn($flag);
        $this->repository->method('save');

        $this->featureFlagService
            ->expects(self::once())
            ->method('invalidateCache')
            ->with(
                self::callback(fn (TenantId $tid) => self::TENANT_ID === $tid->toString()),
                'my_flag'
            );

        $handler($command);
    }

    // -----------------------------------------------------------------------
    // UpdateFeatureFlagConfigCommandHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function updateConfigHandlerUpdatesConfiguration(): void
    {
        $handler = new UpdateFeatureFlagConfigCommandHandler($this->repository, $this->featureFlagService);
        $flag = $this->createEnabledFlag('settings_feature');
        $newConfig = ['max_users' => 500, 'beta' => true];
        $command = new UpdateFeatureFlagConfigCommand(self::TENANT_ID, 'settings_feature', $newConfig);

        $this->repository->method('findByTenantAndFeature')->willReturn($flag);
        $this->repository->expects(self::once())->method('save');
        $this->featureFlagService->expects(self::once())->method('invalidateCache');

        $handler($command);

        self::assertSame($newConfig, $flag->configuration());
    }

    #[Test]
    public function updateConfigHandlerThrowsWhenFlagNotFound(): void
    {
        $handler = new UpdateFeatureFlagConfigCommandHandler($this->repository, $this->featureFlagService);
        $command = new UpdateFeatureFlagConfigCommand(self::TENANT_ID, 'ghost_feature', []);

        $this->repository->method('findByTenantAndFeature')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        $handler($command);
    }

    #[Test]
    public function updateConfigHandlerInvalidatesCache(): void
    {
        $handler = new UpdateFeatureFlagConfigCommandHandler($this->repository, $this->featureFlagService);
        $flag = $this->createEnabledFlag('cache_feature');
        $command = new UpdateFeatureFlagConfigCommand(self::TENANT_ID, 'cache_feature', ['key' => 'val']);

        $this->repository->method('findByTenantAndFeature')->willReturn($flag);
        $this->repository->method('save');

        $this->featureFlagService
            ->expects(self::once())
            ->method('invalidateCache')
            ->with(self::anything(), 'cache_feature');

        $handler($command);
    }

    // -----------------------------------------------------------------------
    // DeleteFeatureFlagCommandHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function deleteHandlerDeletesFlag(): void
    {
        $handler = new DeleteFeatureFlagCommandHandler($this->repository, $this->featureFlagService);
        $flag = $this->createEnabledFlag('delete_me');
        $command = new DeleteFeatureFlagCommand(self::TENANT_ID, 'delete_me');

        $this->repository->method('findByTenantAndFeature')->willReturn($flag);
        $this->repository->expects(self::once())->method('delete')->with($flag);
        $this->featureFlagService->expects(self::once())->method('invalidateCache');

        $handler($command);
    }

    #[Test]
    public function deleteHandlerThrowsWhenFlagNotFound(): void
    {
        $handler = new DeleteFeatureFlagCommandHandler($this->repository, $this->featureFlagService);
        $command = new DeleteFeatureFlagCommand(self::TENANT_ID, 'nonexistent');

        $this->repository->method('findByTenantAndFeature')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        $handler($command);
    }

    #[Test]
    public function deleteHandlerInvalidatesCache(): void
    {
        $handler = new DeleteFeatureFlagCommandHandler($this->repository, $this->featureFlagService);
        $flag = $this->createEnabledFlag('goodbye');
        $command = new DeleteFeatureFlagCommand(self::TENANT_ID, 'goodbye');

        $this->repository->method('findByTenantAndFeature')->willReturn($flag);
        $this->repository->method('delete');

        $this->featureFlagService
            ->expects(self::once())
            ->method('invalidateCache')
            ->with(self::anything(), 'goodbye');

        $handler($command);
    }
}
