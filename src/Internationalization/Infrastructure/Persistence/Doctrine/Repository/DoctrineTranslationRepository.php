<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\Persistence\Doctrine\Repository;

use App\Internationalization\Domain\Model\Translation;
use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationId;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Internationalization\Domain\Repository\TranslationRepositoryInterface;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Connection;

/**
 * Doctrine Translation Repository for the Translation Aggregate Root.
 *
 * Write-side adapter that persists Translation aggregates to ext_translations table.
 * Uses translation_uuid and parameters columns added via migration.
 */
final readonly class DoctrineTranslationRepository implements TranslationRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function save(Translation $translation): void
    {
        $existing = $this->connection->fetchAssociative(
            'SELECT id FROM ext_translations WHERE translation_uuid = ?',
            [$translation->id()->toString()]
        );

        if (false !== $existing) {
            $this->update($translation, (int) $existing['id']);
        } else {
            $this->insert($translation);
        }
    }

    public function findById(TranslationId $id, TenantId $tenantId): ?Translation
    {
        $data = $this->connection->fetchAssociative(
            'SELECT * FROM ext_translations WHERE translation_uuid = ? AND foreign_key LIKE ?',
            [$id->toString(), $tenantId->toString().':%']
        );

        return false !== $data ? $this->hydrate($data) : null;
    }

    public function findByKey(
        TenantId $tenantId,
        LanguageCode $locale,
        TranslationDomain $domain,
        TranslationKey $key,
    ): ?Translation {
        $foreignKey = $tenantId->toString().':'.$key->value();

        $data = $this->connection->fetchAssociative(
            'SELECT * FROM ext_translations WHERE locale = ? AND field = ? AND foreign_key = ?',
            [$locale->value(), $domain->value(), $foreignKey]
        );

        return false !== $data ? $this->hydrate($data) : null;
    }

    public function delete(TranslationId $id, TenantId $tenantId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM ext_translations WHERE translation_uuid = ? AND foreign_key LIKE ?',
            [$id->toString(), $tenantId->toString().':%']
        );
    }

    private function insert(Translation $translation): void
    {
        $this->connection->insert('ext_translations', [
            'locale' => $translation->locale()->value(),
            'object_class' => 'App\\Translation\\CustomTranslation',
            'field' => $translation->domain()->value(),
            'foreign_key' => $translation->tenantId()->toString().':'.$translation->key()->value(),
            'content' => $translation->value()->value(),
            'translation_uuid' => $translation->id()->toString(),
            'parameters' => json_encode($translation->parameters(), JSON_THROW_ON_ERROR),
        ]);
    }

    private function update(Translation $translation, int $legacyId): void
    {
        $this->connection->update(
            'ext_translations',
            [
                'content' => $translation->value()->value(),
                'parameters' => json_encode($translation->parameters(), JSON_THROW_ON_ERROR),
            ],
            ['id' => $legacyId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrate(array $data): Translation
    {
        [$tenantIdStr, $keyStr] = explode(':', (string) $data['foreign_key'], 2);

        $parameters = [];
        if (!empty($data['parameters'])) {
            $decoded = json_decode((string) $data['parameters'], true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                $parameters = $decoded;
            }
        }

        $uuid = !empty($data['translation_uuid'])
            ? TranslationId::fromString((string) $data['translation_uuid'])
            : TranslationId::generate();

        return Translation::reconstitute(
            id: $uuid,
            tenantId: TenantId::fromString($tenantIdStr),
            domain: TranslationDomain::fromString((string) $data['field']),
            key: TranslationKey::fromString($keyStr),
            locale: LanguageCode::fromString((string) $data['locale']),
            value: TranslationValue::fromString((string) ($data['content'] ?? '')),
            parameters: $parameters,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable((string) $data['created_at']) : new \DateTimeImmutable(),
            updatedAt: isset($data['updated_at']) ? new \DateTimeImmutable((string) $data['updated_at']) : new \DateTimeImmutable(),
        );
    }
}
