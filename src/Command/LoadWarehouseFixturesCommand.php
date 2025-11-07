<?php

declare(strict_types=1);

namespace App\Command;

use App\Inventory\Infrastructure\Fixtures\WarehouseFixtures;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:load-warehouse-fixtures',
    description: 'Load warehouse fixtures only',
)]
final class LoadWarehouseFixturesCommand extends Command
{
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly MessageBusInterface $commandBus
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Loading Warehouse Fixtures');

        $fixtures = new WarehouseFixtures($this->commandBus);
        $manager = $this->doctrine->getManager();

        try {
            $fixtures->load($manager);
            $io->success('Warehouse fixtures loaded successfully!');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Failed to load warehouse fixtures: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
