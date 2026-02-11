<?php

declare(strict_types=1);

namespace App\Tenant\Infrastructure\Command;

use App\Tenant\Infrastructure\Persistence\Doctrine\Entity\TenantEntity;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Simple Demo for Translatable Tenant.
 *
 * This command provides a simple demonstration of translatable tenant functionality
 * by directly inserting translations into the ext_translations table.
 */
#[AsCommand(
    name: 'app:demo:simple-translatable',
    description: 'Simple demo of Translatable and Sluggable features',
)]
final class SimpleDemoTranslatableCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Simple Demo: Translatable & Sluggable Tenant');

        // Step 1: Create tenant
        $io->section('Step 1: Creating Tenant');

        $tenantId = 'demo-'.uniqid();
        $tenant = new TenantEntity(
            id: $tenantId,
            name: 'Global Tech Solutions',
            ownerEmail: 'demo-'.uniqid().'@globaltech.com',
            status: 'active',
            createdAt: new \DateTimeImmutable()
        );

        $tenant->setDescription('A multinational technology company providing cutting-edge solutions.');

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        $io->success(sprintf(
            "Created tenant:\n  ID: %s\n  Name: %s\n  Slug: %s",
            $tenant->getId(),
            $tenant->getName(),
            $tenant->getSlug()
        ));

        // Step 2: Add translations manually
        $io->section('Step 2: Adding Translations');

        $objectClass = TenantEntity::class;

        // French translations
        $this->connection->insert('ext_translations', [
            'locale' => 'fr',
            'object_class' => $objectClass,
            'field' => 'name',
            'foreign_key' => $tenantId,
            'content' => 'Solutions Technologiques Globales',
        ]);

        $this->connection->insert('ext_translations', [
            'locale' => 'fr',
            'object_class' => $objectClass,
            'field' => 'description',
            'foreign_key' => $tenantId,
            'content' => 'Une entreprise technologique multinationale fournissant des solutions de pointe.',
        ]);

        // German translations
        $this->connection->insert('ext_translations', [
            'locale' => 'de',
            'object_class' => $objectClass,
            'field' => 'name',
            'foreign_key' => $tenantId,
            'content' => 'Globale Tech-Lösungen',
        ]);

        $this->connection->insert('ext_translations', [
            'locale' => 'de',
            'object_class' => $objectClass,
            'field' => 'description',
            'foreign_key' => $tenantId,
            'content' => 'Ein multinationales Technologieunternehmen, das modernste Lösungen anbietet.',
        ]);

        $io->success('Added French and German translations to ext_translations table');

        // Step 3: Verify database
        $io->section('Step 3: Database Verification');

        $translations = $this->connection->fetchAllAssociative(
            'SELECT locale, field, content FROM ext_translations WHERE foreign_key = ? ORDER BY locale, field',
            [$tenantId]
        );

        $io->table(
            ['Locale', 'Field', 'Content'],
            array_map(fn ($row) => [
                $row['locale'],
                $row['field'],
                substr($row['content'], 0, 50).(strlen($row['content']) > 50 ? '...' : ''),
            ], $translations)
        );

        // Step 4: API Testing Instructions
        $io->section('Step 4: Test via API');

        $io->writeln('Test the translations via API Platform:');
        $io->newLine();

        $io->writeln('<comment>English (default):</comment>');
        $io->writeln(sprintf('  curl "http://127.0.0.1:8000/api/tenants/%s" -H "Accept: application/json"', $tenantId));
        $io->newLine();

        $io->writeln('<comment>French:</comment>');
        $io->writeln(sprintf('  curl "http://127.0.0.1:8000/api/tenants/%s?locale=fr" -H "Accept: application/json"', $tenantId));
        $io->newLine();

        $io->writeln('<comment>German:</comment>');
        $io->writeln(sprintf('  curl "http://127.0.0.1:8000/api/tenants/%s?locale=de" -H "Accept: application/json"', $tenantId));
        $io->newLine();

        // Step 5: Summary
        $io->section('Summary');

        $io->success('Demo completed!');

        $io->writeln('What was demonstrated:');
        $io->listing([
            'Created a tenant with English name and description',
            'Slug was automatically generated: "'.$tenant->getSlug().'"',
            'Added French and German translations to ext_translations table',
            'Translations can now be retrieved via API with ?locale parameter',
        ]);

        $io->note(sprintf('Tenant ID: %s', $tenantId));
        $io->writeln('The tenant will remain in the database for API testing.');

        return Command::SUCCESS;
    }
}
