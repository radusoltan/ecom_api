<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Tenant\Application\Command\CreateTenantCommand;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Tenant fixtures - must run first before other fixtures
 */
class A_TenantFixtures extends Fixture
{
    public function __construct(
        private readonly MessageBusInterface $commandBus
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        echo "🏢 Creating demo tenant...\n";

        $command = new CreateTenantCommand(
            name: 'Demo Store',
            ownerEmail: 'demo@example.com'
        );

        $this->commandBus->dispatch($command);

        echo "   ✓ Demo tenant created successfully\n";
    }

    public function getOrder(): int
    {
        // Run first before all other fixtures
        return 1;
    }
}
