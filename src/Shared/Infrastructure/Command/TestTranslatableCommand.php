<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Command;

use App\Internationalization\Infrastructure\Persistence\Doctrine\Entity\TestTranslatableEntity;
use App\Shared\Infrastructure\Doctrine\Service\TranslatableHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Test Translatable Command.
 *
 * This command tests the Doctrine Translatable service by:
 * 1. Creating a test entity with English content
 * 2. Adding French and German translations
 * 3. Retrieving translations in different locales
 * 4. Displaying all translations
 */
#[AsCommand(
    name: 'app:test:translatable',
    description: 'Test Doctrine Translatable behavior with Gedmo extensions',
)]
final class TestTranslatableCommand extends Command
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

        $io->title('Testing Doctrine Translatable Behavior');

        // Step 1: Create entity with English content (default locale)
        $io->section('Step 1: Creating test entity with English content');
        $entity = new TestTranslatableEntity(
            name: 'Test Product',
            description: 'This is a test product description in English.'
        );

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $io->success(sprintf(
            'Created entity with ID: %d, Slug: %s',
            $entity->getId(),
            $entity->getSlug()
        ));

        // Step 2: Add French translations
        $io->section('Step 2: Adding French translations');
        $this->translatableHelper->setTranslation($entity, 'name', 'fr', 'Produit de Test');
        $this->translatableHelper->setTranslation($entity, 'description', 'fr', 'Ceci est une description de produit test en français.');
        $this->entityManager->flush();

        $io->success('Added French translations');

        // Step 3: Add German translations
        $io->section('Step 3: Adding German translations');
        $this->translatableHelper->setTranslation($entity, 'name', 'de', 'Testprodukt');
        $this->translatableHelper->setTranslation($entity, 'description', 'de', 'Dies ist eine Testproduktbeschreibung auf Deutsch.');
        $this->entityManager->flush();

        $io->success('Added German translations');

        // Step 4: Retrieve translations
        $io->section('Step 4: Retrieving all translations');

        $allTranslations = $this->translatableHelper->findTranslations($entity);

        $io->table(
            ['Locale', 'Field', 'Value'],
            $this->flattenTranslations($allTranslations)
        );

        // Step 5: Test refreshing entity in different locale
        $io->section('Step 5: Testing entity refresh in different locales');

        $io->writeln('Current values (default locale - en):');
        $io->writeln('  Name: '.$entity->getName());
        $io->writeln('  Description: '.$entity->getDescription());

        $io->writeln('');
        $io->writeln('Refreshing entity in French (fr)...');
        $this->translatableHelper->refreshEntityInLocale($entity, 'fr');
        $io->writeln('  Name: '.$entity->getName());
        $io->writeln('  Description: '.$entity->getDescription());

        $io->writeln('');
        $io->writeln('Refreshing entity in German (de)...');
        $this->translatableHelper->refreshEntityInLocale($entity, 'de');
        $io->writeln('  Name: '.$entity->getName());
        $io->writeln('  Description: '.$entity->getDescription());

        // Step 6: Test locale detection
        $io->section('Step 6: Testing locale detection');
        $io->writeln('Current locale: '.$this->translatableHelper->getCurrentLocale());
        $io->writeln('Supported locales: '.implode(', ', $this->translatableHelper->getSupportedLocales()));

        // Clean up
        $io->section('Cleanup');
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
        $io->success('Test entity removed');

        $io->success('All tests passed! Translatable service is working correctly.');

        return Command::SUCCESS;
    }

    /**
     * Flatten translations array for table display.
     *
     * @param array<string, array<string, string>> $translations
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function flattenTranslations(array $translations): array
    {
        $rows = [];

        foreach ($translations as $locale => $fields) {
            foreach ($fields as $field => $value) {
                $rows[] = [$locale, $field, $value];
            }
        }

        return $rows;
    }
}
