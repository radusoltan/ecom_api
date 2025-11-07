<?php

declare(strict_types=1);

namespace App\Tests\Integration\Internationalization;

use App\Shared\Infrastructure\Doctrine\Service\TranslatableHelper;
use App\Tenant\Infrastructure\Persistence\Doctrine\Entity\TenantEntity;
use App\Tests\Support\TenantTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GedmoTranslatablePersistenceTest extends KernelTestCase
{
    use TenantTestTrait;
    private EntityManagerInterface $entityManager;
    private TranslatableHelper $translatableHelper;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->translatableHelper = $container->get(TranslatableHelper::class);

        // Set tenant context for RLS
        $this->setTenantContext($this->getDefaultTenantId()->toString());

        // Clean up test tenants
        $this->cleanupTestTenants();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestTenants();
        parent::tearDown();
    }

    private function cleanupTestTenants(): void
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(TenantEntity::class, 't')
            ->where('t.name LIKE :prefix')
            ->setParameter('prefix', 'Test Tenant%')
            ->getQuery()
            ->execute();
    }

    public function testTenantNameIsTranslatable(): void
    {
        $tenant = new TenantEntity(
            id: 'test-'.uniqid(),
            name: 'Test Tenant Corporation',
            ownerEmail: 'owner@test.com',
            status: 'active',
            createdAt: new \DateTimeImmutable()
        );

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        // Add French translation
        $this->translatableHelper->setTranslation($tenant, 'name', 'fr', 'Test Société Locataire');
        $this->entityManager->flush();

        // Verify translation exists
        $frenchName = $this->translatableHelper->getTranslation($tenant, 'name', 'fr');

        $this->assertSame('Test Société Locataire', $frenchName);
    }

    public function testTenantDescriptionIsTranslatable(): void
    {
        $tenant = new TenantEntity(
            id: 'test-desc-'.uniqid(),
            name: 'Test Tenant Description',
            ownerEmail: 'owner@testdesc.com',
            status: 'active',
            createdAt: new \DateTimeImmutable()
        );
        $tenant->setDescription('A test tenant for description translation');

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        // Add German translation
        $this->translatableHelper->setTranslation($tenant, 'description', 'de', 'Ein Test-Mandant für Beschreibungsübersetzung');
        $this->entityManager->flush();

        // Verify translation exists
        $germanDesc = $this->translatableHelper->getTranslation($tenant, 'description', 'de');

        $this->assertSame('Ein Test-Mandant für Beschreibungsübersetzung', $germanDesc);
    }

    public function testTenantSlugIsGenerated(): void
    {
        $tenant = new TenantEntity(
            id: 'test-slug-'.uniqid(),
            name: 'Test Tenant Sluggable',
            ownerEmail: 'owner@testslug.com',
            status: 'active',
            createdAt: new \DateTimeImmutable()
        );

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        // Slug should be auto-generated
        $slug = $tenant->getSlug();

        $this->assertNotEmpty($slug);
        $this->assertStringContainsString('test-tenant-sluggable', $slug);
    }

    public function testMultipleTranslationsForSameTenant(): void
    {
        $tenant = new TenantEntity(
            id: 'test-multi-'.uniqid(),
            name: 'Test Tenant Multi Lang',
            ownerEmail: 'owner@multi.com',
            status: 'active',
            createdAt: new \DateTimeImmutable()
        );
        $tenant->setDescription('English description for multi-language test');

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        // Add French translations
        $this->translatableHelper->setTranslation($tenant, 'name', 'fr', 'Test Locataire Multi Langue');
        $this->translatableHelper->setTranslation($tenant, 'description', 'fr', 'Description française pour test multilingue');

        // Add German translations
        $this->translatableHelper->setTranslation($tenant, 'name', 'de', 'Test Mieter Mehrsprachig');
        $this->translatableHelper->setTranslation($tenant, 'description', 'de', 'Deutsche Beschreibung für Mehrsprachigkeitstest');

        $this->entityManager->flush();

        // Verify all translations exist
        $allTranslations = $this->translatableHelper->findTranslations($tenant);

        $this->assertArrayHasKey('fr', $allTranslations);
        $this->assertArrayHasKey('de', $allTranslations);
        $this->assertSame('Test Locataire Multi Langue', $allTranslations['fr']['name']);
        $this->assertSame('Test Mieter Mehrsprachig', $allTranslations['de']['name']);
    }

    public function testTranslationsPersistInDatabase(): void
    {
        $tenantId = 'test-persist-'.uniqid();

        $tenant = new TenantEntity(
            id: $tenantId,
            name: 'Test Tenant Persistence',
            ownerEmail: 'owner@persist.com',
            status: 'active',
            createdAt: new \DateTimeImmutable()
        );
        $tenant->setDescription('Testing database persistence');

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        // Add translations
        $this->translatableHelper->setTranslation($tenant, 'name', 'fr', 'Test Persistance Locataire');
        $this->translatableHelper->setTranslation($tenant, 'description', 'fr', 'Test de persistance en base');
        $this->entityManager->flush();

        // Clear entity manager
        $this->entityManager->clear();

        // Reload from database
        $reloadedTenant = $this->entityManager->find(TenantEntity::class, $tenantId);
        $this->assertNotNull($reloadedTenant);

        // Verify translations still exist
        $frenchName = $this->translatableHelper->getTranslation($reloadedTenant, 'name', 'fr');
        $frenchDesc = $this->translatableHelper->getTranslation($reloadedTenant, 'description', 'fr');

        $this->assertSame('Test Persistance Locataire', $frenchName);
        $this->assertSame('Test de persistance en base', $frenchDesc);
    }

    public function testTranslationsAreStoredInExtTranslationsTable(): void
    {
        $tenantId = 'test-table-'.uniqid();

        $tenant = new TenantEntity(
            id: $tenantId,
            name: 'Test Tenant Table Storage',
            ownerEmail: 'owner@table.com',
            status: 'active',
            createdAt: new \DateTimeImmutable()
        );

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        // Add German translation
        $this->translatableHelper->setTranslation($tenant, 'name', 'de', 'Test Mieter Tabellenspeicherung');
        $this->entityManager->flush();

        // Query ext_translations table directly
        $sql = "SELECT locale, field, content FROM ext_translations
                WHERE object_class LIKE '%TenantEntity'
                AND foreign_key = :foreignKey
                AND locale = :locale";

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $result = $stmt->executeQuery([
            'foreignKey' => $tenantId,
            'locale' => 'de',
        ]);

        $translations = $result->fetchAllAssociative();

        $this->assertNotEmpty($translations);
        $this->assertCount(1, $translations);
        $this->assertSame('de', $translations[0]['locale']);
        $this->assertSame('name', $translations[0]['field']);
        $this->assertSame('Test Mieter Tabellenspeicherung', $translations[0]['content']);
    }

    public function testUpdateTranslationOverwritesExisting(): void
    {
        $tenant = new TenantEntity(
            id: 'test-update-'.uniqid(),
            name: 'Test Tenant Update',
            ownerEmail: 'owner@update.com',
            status: 'active',
            createdAt: new \DateTimeImmutable()
        );

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        // Set initial translation
        $this->translatableHelper->setTranslation($tenant, 'name', 'fr', 'Version Initiale');
        $this->entityManager->flush();

        // Update translation
        $this->translatableHelper->setTranslation($tenant, 'name', 'fr', 'Version Mise à Jour');
        $this->entityManager->flush();

        // Verify updated value
        $frenchName = $this->translatableHelper->getTranslation($tenant, 'name', 'fr');

        $this->assertSame('Version Mise à Jour', $frenchName);
    }

    public function testRefreshEntityLoadsTranslatedValues(): void
    {
        $tenant = new TenantEntity(
            id: 'test-refresh-'.uniqid(),
            name: 'Test Tenant Refresh',
            ownerEmail: 'owner@refresh.com',
            status: 'active',
            createdAt: new \DateTimeImmutable()
        );
        $tenant->setDescription('Original English description');

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        // Add French translations
        $this->translatableHelper->setTranslation($tenant, 'name', 'fr', 'Test Locataire Rafraîchir');
        $this->translatableHelper->setTranslation($tenant, 'description', 'fr', 'Description française originale');
        $this->entityManager->flush();

        // Refresh entity in French locale
        $this->translatableHelper->refreshEntityInLocale($tenant, 'fr');

        // Entity should now have French values
        $this->assertSame('Test Locataire Rafraîchir', $tenant->getName());
        $this->assertSame('Description française originale', $tenant->getDescription());
    }

    public function testGedmoTranslationEntityAttribute(): void
    {
        // This test verifies that the #[Gedmo\TranslationEntity] attribute is properly configured
        $tenant = new TenantEntity(
            id: 'test-attr-'.uniqid(),
            name: 'Test Tenant Attribute',
            ownerEmail: 'owner@attr.com',
            status: 'active',
            createdAt: new \DateTimeImmutable()
        );

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        // Add translation (this will fail if TranslationEntity attribute is not properly set)
        $this->translatableHelper->setTranslation($tenant, 'name', 'de', 'Test Mieter Attribut');
        $this->entityManager->flush();

        // Verify it was stored
        $germanName = $this->translatableHelper->getTranslation($tenant, 'name', 'de');

        $this->assertSame('Test Mieter Attribut', $germanName);
    }

    public function testTranslationWithNullDescription(): void
    {
        $tenant = new TenantEntity(
            id: 'test-null-'.uniqid(),
            name: 'Test Tenant Null Desc',
            ownerEmail: 'owner@null.com',
            status: 'active',
            createdAt: new \DateTimeImmutable()
        );
        // Description is null by default

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        // Add translation for null field
        $this->translatableHelper->setTranslation($tenant, 'description', 'fr', 'Description ajoutée en français');
        $this->entityManager->flush();

        // Verify translation exists
        $frenchDesc = $this->translatableHelper->getTranslation($tenant, 'description', 'fr');

        $this->assertSame('Description ajoutée en français', $frenchDesc);
    }

    public function testSlugIsUniquePerTenant(): void
    {
        $tenant1 = new TenantEntity(
            id: 'test-slug1-'.uniqid(),
            name: 'Test Tenant Unique Slug',
            ownerEmail: 'owner1@slug.com',
            status: 'active',
            createdAt: new \DateTimeImmutable()
        );

        $tenant2 = new TenantEntity(
            id: 'test-slug2-'.uniqid(),
            name: 'Test Tenant Unique Slug',
            ownerEmail: 'owner2@slug.com',
            status: 'active',
            createdAt: new \DateTimeImmutable()
        );

        $this->entityManager->persist($tenant1);
        $this->entityManager->flush();

        $this->entityManager->persist($tenant2);
        $this->entityManager->flush();

        $slug1 = $tenant1->getSlug();
        $slug2 = $tenant2->getSlug();

        // Slugs should be different (Gedmo adds suffix for duplicates)
        $this->assertNotSame($slug1, $slug2);
    }
}
