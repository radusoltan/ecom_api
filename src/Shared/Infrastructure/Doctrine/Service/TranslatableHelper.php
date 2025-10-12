<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Service;

use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Gedmo\Translatable\TranslatableListener;

/**
 * Translatable Helper Service
 *
 * Provides utility methods for managing entity translations using Gedmo Translatable.
 *
 * Features:
 * - Set translations for specific entity fields
 * - Get translations for specific locales
 * - Retrieve all translations for an entity
 * - Switch entity locale temporarily
 *
 * Example usage:
 * ```php
 * // Set French translation for a product name
 * $translatableHelper->setTranslation($product, 'name', 'fr', 'Produit Example');
 *
 * // Get German translation of product name
 * $germanName = $translatableHelper->getTranslation($product, 'name', 'de');
 *
 * // Get all translations for product
 * $allTranslations = $translatableHelper->findTranslations($product);
 * ```
 */
final readonly class TranslatableHelper
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslatableListener $translatableListener,
        private LocaleProvider $localeProvider,
    ) {
    }

    /**
     * Set a translation for a specific entity field
     *
     * @param object $entity The translatable entity
     * @param string $field The field name to translate
     * @param string $locale The locale code (en, fr, de)
     * @param string $value The translated value
     */
    public function setTranslation(object $entity, string $field, string $locale, string $value): void
    {
        $repository = $this->getTranslationRepository();

        // Don't save empty translations - they are considered as "no translation"
        if ($value === '') {
            // If there's an existing translation, remove it
            $entityClass = get_class($entity);
            $entityId = $this->entityManager->getClassMetadata($entityClass)->getIdentifierValues($entity);
            $foreignKey = (string) reset($entityId);

            $qb = $this->entityManager->createQueryBuilder();
            $qb->delete(\App\Internationalization\Infrastructure\Persistence\Doctrine\Entity\Translation::class, 't')
                ->where('t.locale = :locale')
                ->andWhere('t.objectClass = :objectClass')
                ->andWhere('t.foreignKey = :foreignKey')
                ->andWhere('t.field = :field')
                ->setParameter('locale', $locale)
                ->setParameter('objectClass', $entityClass)
                ->setParameter('foreignKey', $foreignKey)
                ->setParameter('field', $field)
                ->getQuery()
                ->execute();
            return;
        }

        $repository->translate($entity, $field, $locale, $value);
    }

    /**
     * Get a translation for a specific entity field and locale
     *
     * @param object $entity The translatable entity
     * @param string $field The field name
     * @param string $locale The locale code (en, fr, de)
     * @return string|null The translated value or null if not found
     */
    public function getTranslation(object $entity, string $field, string $locale): ?string
    {
        $translations = $this->findTranslations($entity);

        return $translations[$locale][$field] ?? null;
    }

    /**
     * Get all translations for an entity
     *
     * Returns array structure:
     * [
     *   'fr' => ['name' => 'Produit', 'description' => 'Description en français'],
     *   'de' => ['name' => 'Produkt', 'description' => 'Beschreibung auf Deutsch'],
     * ]
     *
     * @param object $entity The translatable entity
     * @return array<string, array<string, string>>
     */
    public function findTranslations(object $entity): array
    {
        $repository = $this->getTranslationRepository();

        return $repository->findTranslations($entity);
    }

    /**
     * Refresh entity with translations for a specific locale
     *
     * This temporarily changes the entity's translatable fields to the requested locale.
     * Useful when you need to fetch an entity in a different language.
     *
     * @param object $entity The translatable entity
     * @param string $locale The locale code (en, fr, de)
     */
    public function refreshEntityInLocale(object $entity, string $locale): void
    {
        // Save current locale
        $currentLocale = $this->translatableListener->getListenerLocale();

        // Set the locale for the translatable listener
        $this->translatableListener->setTranslatableLocale($locale);

        // Set locale on the entity if it implements setTranslatableLocale
        if (method_exists($entity, 'setTranslatableLocale')) {
            $entity->setTranslatableLocale($locale);
        }

        // Clear entity from identity map and reload with new locale
        $this->entityManager->refresh($entity);

        // Restore original locale on listener
        $this->translatableListener->setTranslatableLocale($currentLocale);
    }

    /**
     * Get the current active locale
     */
    public function getCurrentLocale(): string
    {
        return $this->localeProvider->getCurrentLocale();
    }

    /**
     * Get all supported locales
     *
     * @return string[]
     */
    public function getSupportedLocales(): array
    {
        return $this->localeProvider->getSupportedLocales();
    }

    /**
     * Check if an entity has translations for a specific locale
     *
     * @param object $entity The translatable entity
     * @param string $locale The locale code
     */
    public function hasTranslationsForLocale(object $entity, string $locale): bool
    {
        $translations = $this->findTranslations($entity);

        return isset($translations[$locale]) && !empty($translations[$locale]);
    }

    /**
     * Remove all translations for a specific locale
     *
     * @param object $entity The translatable entity
     * @param string $locale The locale code
     */
    public function removeTranslationsForLocale(object $entity, string $locale): void
    {
        $translations = $this->findTranslations($entity);

        if (!isset($translations[$locale])) {
            return;
        }

        // Delete translations directly from database
        $entityClass = get_class($entity);
        $entityId = $this->entityManager->getClassMetadata($entityClass)->getIdentifierValues($entity);
        $foreignKey = (string) reset($entityId);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(\App\Internationalization\Infrastructure\Persistence\Doctrine\Entity\Translation::class, 't')
            ->where('t.locale = :locale')
            ->andWhere('t.objectClass = :objectClass')
            ->andWhere('t.foreignKey = :foreignKey')
            ->setParameter('locale', $locale)
            ->setParameter('objectClass', $entityClass)
            ->setParameter('foreignKey', $foreignKey)
            ->getQuery()
            ->execute();
    }

    /**
     * Get the translation repository
     */
    private function getTranslationRepository(): TranslationRepository
    {
        // Use our custom Translation entity that extends AbstractTranslation
        /** @var TranslationRepository $repository */
        $repository = $this->entityManager->getRepository(
            \App\Internationalization\Infrastructure\Persistence\Doctrine\Entity\Translation::class
        );

        return $repository;
    }
}
