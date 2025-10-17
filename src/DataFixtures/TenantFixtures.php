<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Tenant\Application\Command\CreateTenantCommand;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Tenant fixtures - creates multiple tenants for testing
 */
class TenantFixtures extends Fixture
{
    public function __construct(
        private readonly MessageBusInterface $commandBus
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        echo "🏢 Creating tenants...\n";

        // Tenant 1: TechMart
        $command1 = new CreateTenantCommand(
            name: 'TechMart',
            ownerEmail: 'owner@techmart.com'
        );
        $this->commandBus->dispatch($command1);
        echo "   ✓ TechMart created\n";

        // Tenant 2: Fashion Hub
        $command2 = new CreateTenantCommand(
            name: 'Fashion Hub',
            ownerEmail: 'owner@fashionhub.com'
        );
        $this->commandBus->dispatch($command2);
        echo "   ✓ Fashion Hub created\n";

        // Tenant 3: HomeGoods Plus
        $command3 = new CreateTenantCommand(
            name: 'HomeGoods Plus',
            ownerEmail: 'owner@homegoods.com'
        );
        $this->commandBus->dispatch($command3);
        echo "   ✓ HomeGoods Plus created\n";

        echo "✅ All tenants created successfully (3 total)\n";
    }

    public function getOrder(): int
    {
        return 1;
    }
}
