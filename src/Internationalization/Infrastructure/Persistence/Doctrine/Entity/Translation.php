<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Translation Entity for Gedmo Translatable
 *
 * This entity stores all translations for translatable fields across all entities.
 * It uses pure Doctrine attributes (no annotations) to work with Doctrine ORM 3.
 *
 * This is a "personal translation entity" as documented in Gedmo Translatable.
 * Instead of extending AbstractTranslation (which has mixed annotations/attributes),
 * we define all properties directly with PHP 8 attributes only.
 *
 * Table structure:
 * - id: Auto-incrementing primary key
 * - locale: Language code (en, fr, de)
 * - object_class: Fully qualified class name of the translated entity
 * - field: The field name being translated
 * - foreign_key: The ID of the entity being translated
 * - content: The translated value
 */
#[ORM\Entity(repositoryClass: 'Gedmo\Translatable\Entity\Repository\TranslationRepository')]
#[ORM\Table(name: 'ext_translations')]
#[ORM\Index(name: 'translations_lookup_idx', columns: ['locale', 'object_class', 'foreign_key'])]
#[ORM\UniqueConstraint(name: 'lookup_unique_idx', columns: ['locale', 'object_class', 'field', 'foreign_key'])]
class Translation
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 8)]
    private ?string $locale = null;

    #[ORM\Column(name: 'object_class', type: Types::STRING, length: 191)]
    private ?string $objectClass = null;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private ?string $field = null;

    #[ORM\Column(name: 'foreign_key', type: Types::STRING, length: 64)]
    private ?string $foreignKey = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setObjectClass(?string $objectClass): self
    {
        $this->objectClass = $objectClass;
        return $this;
    }

    public function getObjectClass(): ?string
    {
        return $this->objectClass;
    }

    public function setField(?string $field): self
    {
        $this->field = $field;
        return $this;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    public function setForeignKey(?string $foreignKey): self
    {
        $this->foreignKey = $foreignKey;
        return $this;
    }

    public function getForeignKey(): ?string
    {
        return $this->foreignKey;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }
}
