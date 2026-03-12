<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Shared\Infrastructure\Tenant\TenantContext;
use App\Tenant\Application\Command\CreateTenantCommand;
use App\Tenant\Application\Command\EnableLocaleCommand;
use App\Tenant\Application\Command\SetDefaultLocaleCommand;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

final class TenantFixtures extends AbstractTenantAwareFixture implements FixtureGroupInterface
{
    public function __construct(
        EntityManagerInterface $entityManager,
        TenantContext $tenantContext,
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct($entityManager, $tenantContext);
    }

    public static function getGroups(): array
    {
        return ['sprint3', 'sprint3-tenants'];
    }

    public function load(ObjectManager $manager): void
    {
        echo "🏢 Creating Sprint 3 tenants...\n";

        foreach (Sprint3SeedData::tenants() as $tenant) {
            $this->commandBus->dispatch(new CreateTenantCommand(
                name: $tenant['name'],
                ownerEmail: $tenant['ownerEmail'],
            ));

            $tenantId = (string) $this->connection()->fetchOne(
                'SELECT id FROM tenants WHERE owner_email = :ownerEmail LIMIT 1',
                ['ownerEmail' => $tenant['ownerEmail']]
            );

            $this->activateTenantContext($tenantId);
            $this->commandBus->dispatch(new SetDefaultLocaleCommand($tenantId, 'en'));
            $this->commandBus->dispatch(new EnableLocaleCommand($tenantId, 'fr'));
            $this->commandBus->dispatch(new EnableLocaleCommand($tenantId, 'de'));

            echo sprintf("   ✓ %s (%s)\n", $tenant['name'], $tenantId);
        }

        $this->clearTenantContext();

        echo "✅ Tenants ready with EN/FR/DE locales\n";
    }
}
