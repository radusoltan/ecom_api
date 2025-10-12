<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Command;

use App\Shared\Infrastructure\Doctrine\Service\TranslatableHelper;
use App\Tenant\Infrastructure\Persistence\Doctrine\Entity\TenantEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Demo Command for Translatable and Sluggable Tenant
 *
 * This command demonstrates:
 * 1. Creating a tenant with English content
 * 2. Adding French and German translations
 * 3. Auto-generating slugs in different languages
 * 4. Retrieving tenant in different locales
 * 5. Displaying all translations
 */
#[AsCommand(
    name: 'app:demo:translatable-tenant',
    description: 'Demo Gedmo Translatable and Sluggable with Tenant entity',
)]
final class DemoTranslatableTenantCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatableHelper $translatableHelper,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Demo: Translatable & Sluggable Tenant');
        $io->writeln('This demo showcases Gedmo Translatable and Sluggable behaviors with the Tenant entity.');
        $io->newLine();

        // Step 1: Create tenant with English content
        $io->section('Step 1: Creating Tenant with English Content');

        $tenant = new TenantEntity(
            id: 'demo-' . uniqid(),
            name: 'Acme Corporation',
            ownerEmail: 'demo-' . uniqid() . '@acme.com',
            status: 'active',
            createdAt: new \DateTimeImmutable()
        );

        $tenant->setDescription('A leading provider of innovative solutions for modern businesses.');

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        $io->success(sprintf(
            "Created tenant:\n  ID: %s\n  Name: %s\n  Description: %s\n  Slug: %s",
            $tenant->getId(),
            $tenant->getName(),
            $tenant->getDescription(),
            $tenant->getSlug()
        ));

        // Step 2: Add French translations
        $io->section('Step 2: Adding French Translations');

        $this->translatableHelper->setTranslation(
            $tenant,
            'name',
            'fr',
            'Corporation Acme'
        );

        $this->translatableHelper->setTranslation(
            $tenant,
            'description',
            'fr',
            'Un fournisseur leader de solutions innovantes pour les entreprises modernes.'
        );

        $this->entityManager->flush();

        $io->success("Added French translations:\n  Name: Corporation Acme\n  Description: Un fournisseur leader...");

        // Step 3: Add German translations
        $io->section('Step 3: Adding German Translations');

        $this->translatableHelper->setTranslation(
            $tenant,
            'name',
            'de',
            'Acme Konzern'
        );

        $this->translatableHelper->setTranslation(
            $tenant,
            'description',
            'de',
            'Ein führender Anbieter innovativer Lösungen für moderne Unternehmen.'
        );

        $this->entityManager->flush();

        $io->success("Added German translations:\n  Name: Acme Konzern\n  Description: Ein führender Anbieter...");

        // Step 4: Display all translations
        $io->section('Step 4: All Translations in Database');

        $allTranslations = $this->translatableHelper->findTranslations($tenant);

        $rows = [];
        foreach ($allTranslations as $locale => $fields) {
            foreach ($fields as $field => $value) {
                $rows[] = [$locale, $field, substr($value, 0, 60) . (strlen($value) > 60 ? '...' : '')];
            }
        }

        if (!empty($rows)) {
            $io->table(['Locale', 'Field', 'Value'], $rows);
        } else {
            $io->note('No translations found in ext_translations table (default locale stored in main table)');
        }

        // Step 5: Retrieve tenant in different locales
        $io->section('Step 5: Retrieving Tenant in Different Locales');

        // English (default)
        $io->writeln('<info>English (en) - Default Locale:</info>');
        $io->writeln(sprintf('  Name: %s', $tenant->getName()));
        $io->writeln(sprintf('  Description: %s', $tenant->getDescription()));
        $io->writeln(sprintf('  Slug: %s', $tenant->getSlug()));
        $io->newLine();

        // French
        $io->writeln('<info>French (fr):</info>');
        $this->translatableHelper->refreshEntityInLocale($tenant, 'fr');
        $io->writeln(sprintf('  Name: %s', $tenant->getName()));
        $io->writeln(sprintf('  Description: %s', $tenant->getDescription()));
        $io->writeln(sprintf('  Slug: %s', $tenant->getSlug()));
        $io->newLine();

        // German
        $io->writeln('<info>German (de):</info>');
        $this->translatableHelper->refreshEntityInLocale($tenant, 'de');
        $io->writeln(sprintf('  Name: %s', $tenant->getName()));
        $io->writeln(sprintf('  Description: %s', $tenant->getDescription()));
        $io->writeln(sprintf('  Slug: %s', $tenant->getSlug()));
        $io->newLine();

        // Reset to default locale
        $this->translatableHelper->refreshEntityInLocale($tenant, 'en');

        // Step 6: Demonstrate translation helpers
        $io->section('Step 6: Using TranslatableHelper Methods');

        // Check if translations exist
        $hasFrench = $this->translatableHelper->hasTranslationsForLocale($tenant, 'fr');
        $hasSpanish = $this->translatableHelper->hasTranslationsForLocale($tenant, 'es');

        $io->writeln(sprintf('Has French translations: <info>%s</info>', $hasFrench ? 'Yes' : 'No'));
        $io->writeln(sprintf('Has Spanish translations: <info>%s</info>', $hasSpanish ? 'Yes' : 'No'));
        $io->newLine();

        // Get specific translation
        $germanName = $this->translatableHelper->getTranslation($tenant, 'name', 'de');
        $io->writeln(sprintf('German name (via helper): <info>%s</info>', $germanName));
        $io->newLine();

        // Current locale
        $currentLocale = $this->translatableHelper->getCurrentLocale();
        $io->writeln(sprintf('Current application locale: <info>%s</info>', $currentLocale));
        $io->newLine();

        // Supported locales
        $supportedLocales = $this->translatableHelper->getSupportedLocales();
        $io->writeln(sprintf('Supported locales: <info>%s</info>', implode(', ', $supportedLocales)));

        // Step 7: Check database
        $io->section('Step 7: Database Verification');

        $io->writeln('You can verify the data in the database:');
        $io->newLine();

        $io->writeln('<comment>Main table (tenants):</comment>');
        $io->writeln(sprintf('  SELECT id, name, description, slug FROM tenants WHERE id = \'%s\';', $tenant->getId()));
        $io->newLine();

        $io->writeln('<comment>Translations table (ext_translations):</comment>');
        $io->writeln(sprintf(
            '  SELECT locale, field, content FROM ext_translations WHERE object_class LIKE \'%%TenantEntity\' AND foreign_key = \'%s\' ORDER BY locale, field;',
            $tenant->getId()
        ));
        $io->newLine();

        // Step 8: API Testing Instructions
        $io->section('Step 8: Test via API');

        $io->writeln('You can now test the tenant translations via API:');
        $io->newLine();

        $io->writeln('<comment>Get tenant in English (default):</comment>');
        $io->writeln(sprintf('  curl http://127.0.0.1:8000/api/tenants/%s', $tenant->getId()));
        $io->newLine();

        $io->writeln('<comment>Get tenant in French:</comment>');
        $io->writeln(sprintf('  curl "http://127.0.0.1:8000/api/tenants/%s?locale=fr"', $tenant->getId()));
        $io->newLine();

        $io->writeln('<comment>Get tenant in German (via Accept-Language header):</comment>');
        $io->writeln(sprintf('  curl -H "Accept-Language: de" http://127.0.0.1:8000/api/tenants/%s', $tenant->getId()));
        $io->newLine();

        // Cleanup option
        $io->section('Cleanup');

        $io->writeln(sprintf('<fg=yellow>Tenant ID for cleanup: %s</>', $tenant->getId()));
        $io->note('The demo tenant will remain in the database. You can delete it manually if needed.');

        $io->success('Demo completed successfully!');
        $io->writeln('Key takeaways:');
        $io->listing([
            'Translatable fields (name, description) are stored in ext_translations table',
            'Default locale content is stored in the main table',
            'Slugs are automatically generated and translated',
            'Translations can be retrieved via TranslatableHelper or by refreshing the entity',
            'API automatically serves content in the requested locale',
        ]);

        return Command::SUCCESS;
    }
}
