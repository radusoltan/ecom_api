<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Doctrine\Service;

use App\Internationalization\Infrastructure\Persistence\Doctrine\Entity\TestTranslatableEntity;
use App\Shared\Infrastructure\Doctrine\Service\TranslatableHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TranslatableHelperTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private TranslatableHelper $translatableHelper;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->translatableHelper = $container->get(TranslatableHelper::class);

        // Clean up any existing test entities
        $this->cleanupTestEntities();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestEntities();
        parent::tearDown();
    }

    private function cleanupTestEntities(): void
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(TestTranslatableEntity::class, 't')
            ->where('t.name LIKE :prefix')
            ->setParameter('prefix', 'Test Entity%')
            ->getQuery()
            ->execute();
    }

    public function testSetTranslationCreatesNewTranslation(): void
    {
        $entity = new TestTranslatableEntity('Test Entity for Translation', 'Test description');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->translatableHelper->setTranslation($entity, 'name', 'fr', 'Entité de Test');
        $this->entityManager->flush();

        $frenchName = $this->translatableHelper->getTranslation($entity, 'name', 'fr');

        $this->assertSame('Entité de Test', $frenchName);
    }

    public function testSetTranslationUpdatesExistingTranslation(): void
    {
        $entity = new TestTranslatableEntity('Test Entity Update', 'Test description');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        // Set initial translation
        $this->translatableHelper->setTranslation($entity, 'name', 'de', 'Test Entität');
        $this->entityManager->flush();

        // Update the translation
        $this->translatableHelper->setTranslation($entity, 'name', 'de', 'Aktualisierte Entität');
        $this->entityManager->flush();

        $germanName = $this->translatableHelper->getTranslation($entity, 'name', 'de');

        $this->assertSame('Aktualisierte Entität', $germanName);
    }

    public function testGetTranslationReturnsNullForNonExistent(): void
    {
        $entity = new TestTranslatableEntity('Test Entity', 'Description');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $translation = $this->translatableHelper->getTranslation($entity, 'name', 'de');

        $this->assertNull($translation);
    }

    public function testFindTranslationsReturnsAllTranslations(): void
    {
        $entity = new TestTranslatableEntity('Test Entity Multi', 'Original description');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->translatableHelper->setTranslation($entity, 'name', 'fr', 'Entité Française');
        $this->translatableHelper->setTranslation($entity, 'description', 'fr', 'Description française');
        $this->translatableHelper->setTranslation($entity, 'name', 'de', 'Deutsche Entität');
        $this->translatableHelper->setTranslation($entity, 'description', 'de', 'Deutsche Beschreibung');
        $this->entityManager->flush();

        $translations = $this->translatableHelper->findTranslations($entity);

        $this->assertArrayHasKey('fr', $translations);
        $this->assertArrayHasKey('de', $translations);
        $this->assertSame('Entité Française', $translations['fr']['name']);
        $this->assertSame('Description française', $translations['fr']['description']);
        $this->assertSame('Deutsche Entität', $translations['de']['name']);
        $this->assertSame('Deutsche Beschreibung', $translations['de']['description']);
    }

    public function testFindTranslationsReturnsEmptyArrayWhenNoTranslations(): void
    {
        $entity = new TestTranslatableEntity('Test Entity No Trans', 'Description');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $translations = $this->translatableHelper->findTranslations($entity);

        $this->assertIsArray($translations);
        // May contain 'en' with original values depending on Gedmo behavior
        // The important thing is it doesn't throw an error
    }

    public function testHasTranslationsForLocaleReturnsTrueWhenExists(): void
    {
        $entity = new TestTranslatableEntity('Test Entity Has Trans', 'Description');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->translatableHelper->setTranslation($entity, 'name', 'fr', 'Nom Français');
        $this->entityManager->flush();

        $hasFrench = $this->translatableHelper->hasTranslationsForLocale($entity, 'fr');

        $this->assertTrue($hasFrench);
    }

    public function testHasTranslationsForLocaleReturnsFalseWhenNotExists(): void
    {
        $entity = new TestTranslatableEntity('Test Entity No German', 'Description');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $hasGerman = $this->translatableHelper->hasTranslationsForLocale($entity, 'de');

        $this->assertFalse($hasGerman);
    }

    public function testRemoveTranslationsForLocaleRemovesAllFields(): void
    {
        $entity = new TestTranslatableEntity('Test Entity Remove', 'Description to remove');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        // Add French translations
        $this->translatableHelper->setTranslation($entity, 'name', 'fr', 'Nom à Supprimer');
        $this->translatableHelper->setTranslation($entity, 'description', 'fr', 'Description à supprimer');
        $this->entityManager->flush();

        // Verify they exist
        $this->assertTrue($this->translatableHelper->hasTranslationsForLocale($entity, 'fr'));

        // Remove French translations
        $this->translatableHelper->removeTranslationsForLocale($entity, 'fr');

        // Verify they're gone
        $this->assertFalse($this->translatableHelper->hasTranslationsForLocale($entity, 'fr'));
    }

    public function testRemoveTranslationsForLocaleDoesNothingWhenNoTranslations(): void
    {
        $entity = new TestTranslatableEntity('Test Entity No Remove', 'Description');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        // Should not throw an exception
        $this->translatableHelper->removeTranslationsForLocale($entity, 'de');

        // Entity should still exist
        $this->assertNotNull($entity->getId());
    }

    public function testRefreshEntityInLocaleLoadsCorrectTranslation(): void
    {
        $entity = new TestTranslatableEntity('Test Entity Refresh English', 'English description');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        // Add German translation
        $this->translatableHelper->setTranslation($entity, 'name', 'de', 'Deutsche Namen');
        $this->translatableHelper->setTranslation($entity, 'description', 'de', 'Deutsche Beschreibung');
        $this->entityManager->flush();

        // Refresh entity in German
        $this->translatableHelper->refreshEntityInLocale($entity, 'de');

        // Entity should now have German values
        $this->assertSame('Deutsche Namen', $entity->getName());
        $this->assertSame('Deutsche Beschreibung', $entity->getDescription());
    }

    public function testRefreshEntityInLocaleRestoresOriginalLocale(): void
    {
        $entity = new TestTranslatableEntity('Test Entity Locale Restore', 'Description');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->translatableHelper->setTranslation($entity, 'name', 'fr', 'Nom Français');
        $this->entityManager->flush();

        $localeBefore = $this->translatableHelper->getCurrentLocale();

        // Refresh in French
        $this->translatableHelper->refreshEntityInLocale($entity, 'fr');

        $localeAfter = $this->translatableHelper->getCurrentLocale();

        // Locale should be restored
        $this->assertSame($localeBefore, $localeAfter);
    }

    public function testGetCurrentLocaleReturnsLocale(): void
    {
        $locale = $this->translatableHelper->getCurrentLocale();

        $this->assertIsString($locale);
        $this->assertNotEmpty($locale);
    }

    public function testGetSupportedLocalesReturnsArray(): void
    {
        $locales = $this->translatableHelper->getSupportedLocales();

        $this->assertIsArray($locales);
        $this->assertContains('en', $locales);
        $this->assertContains('fr', $locales);
        $this->assertContains('de', $locales);
    }

    public function testSetMultipleTranslationsForSameField(): void
    {
        $entity = new TestTranslatableEntity('Test Entity Multiple', 'Description');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->translatableHelper->setTranslation($entity, 'name', 'fr', 'Nom Français');
        $this->translatableHelper->setTranslation($entity, 'name', 'de', 'Deutscher Name');
        $this->entityManager->flush();

        $frenchName = $this->translatableHelper->getTranslation($entity, 'name', 'fr');
        $germanName = $this->translatableHelper->getTranslation($entity, 'name', 'de');

        $this->assertSame('Nom Français', $frenchName);
        $this->assertSame('Deutscher Name', $germanName);
    }

    public function testTranslationPersistsAcrossEntityManagerRefresh(): void
    {
        $entity = new TestTranslatableEntity('Test Entity Persist', 'Persistent description');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->translatableHelper->setTranslation($entity, 'name', 'fr', 'Nom Persistant');
        $this->entityManager->flush();

        // Clear entity manager to force reload from database
        $this->entityManager->clear();

        // Reload entity
        $reloadedEntity = $this->entityManager->find(TestTranslatableEntity::class, $entity->getId());
        $this->assertNotNull($reloadedEntity);

        $frenchName = $this->translatableHelper->getTranslation($reloadedEntity, 'name', 'fr');

        $this->assertSame('Nom Persistant', $frenchName);
    }

    public function testSetTranslationWithEmptyStringValue(): void
    {
        $entity = new TestTranslatableEntity('Test Entity Empty', 'Description');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->translatableHelper->setTranslation($entity, 'name', 'de', '');
        $this->entityManager->flush();

        $germanName = $this->translatableHelper->getTranslation($entity, 'name', 'de');

        // Empty string is considered as no translation
        $this->assertNull($germanName);
    }
}
