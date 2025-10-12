<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;

/**
 * Test Entity for Translatable Behavior
 *
 * This is a simple test entity to verify Gedmo Translatable behavior is working.
 * It will be used in Task 1.7 for the demo.
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_translatable')]
#[Gedmo\TranslationEntity(class: Translation::class)]
class TestTranslatableEntity implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[Gedmo\Translatable]
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[Gedmo\Slug(fields: ['name'])]
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $slug;

    #[Gedmo\Locale]
    private ?string $locale = null;

    public function __construct(string $name, ?string $description = null)
    {
        $this->name = $name;
        $this->description = $description;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setTranslatableLocale(string $locale): void
    {
        $this->locale = $locale;
    }
}
