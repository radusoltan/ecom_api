<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\Persistence\Doctrine\Repository;

use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationEntry;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Internationalization\Domain\Repository\TranslationEntryRepositoryInterface;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Connection;

/**
 * Doctrine Translation Entry Repository.
 *
 * Adapter implementing TranslationEntryRepositoryInterface.
 * Works with Gedmo's ext_translations table.
 */
final readonly class DoctrineTranslationEntryRepository implements TranslationEntryRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function save(TranslationEntry $entry): void
    {
        if (null === $entry->id) {
            $this->insert($entry);
        } else {
            $this->update($entry);
        }
    }

    private function insert(TranslationEntry $entry): void
    {
        $this->connection->insert('ext_translations', [
            'locale' => $entry->locale->value(),
            'object_class' => 'App\\Translation\\CustomTranslation', // Marker class
            'field' => $entry->domain->value(),
            'foreign_key' => $entry->tenantId->toString().':'.$entry->key->value(),
            'content' => $entry->value->value(),
        ]);
    }

    private function update(TranslationEntry $entry): void
    {
        $this->connection->update(
            'ext_translations',
            ['content' => $entry->value->value()],
            ['id' => $entry->id]
        );
    }

    public function delete(int $id, TenantId $tenantId): void
    {
        $this->connection->delete('ext_translations', ['id' => $id]);
    }

    public function findById(int $id, TenantId $tenantId): ?TranslationEntry
    {
        $data = $this->connection->fetchAssociative(
            'SELECT * FROM ext_translations WHERE id = ?',
            [$id]
        );

        return $data ? $this->hydrateEntry($data) : null;
    }

    public function findByKey(
        TenantId $tenantId,
        LanguageCode $locale,
        TranslationDomain $domain,
        TranslationKey $key,
    ): ?TranslationEntry {
        $foreignKey = $tenantId->toString().':'.$key->value();

        $data = $this->connection->fetchAssociative(
            'SELECT * FROM ext_translations WHERE locale = ? AND field = ? AND foreign_key = ?',
            [$locale->value(), $domain->value(), $foreignKey]
        );

        return $data ? $this->hydrateEntry($data) : null;
    }

    public function findAll(
        TenantId $tenantId,
        array $filters = [],
        int $page = 1,
        int $perPage = 50,
    ): array {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from('ext_translations')
            ->where("object_class = 'App\\\\Translation\\\\CustomTranslation'")
            ->andWhere('foreign_key LIKE :tenant_prefix')
            ->setParameter('tenant_prefix', $tenantId->toString().':%');

        $this->applyFilters($qb, $filters);

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->orderBy('id', 'DESC');

        $results = $qb->executeQuery()->fetchAllAssociative();

        return array_map(fn ($data) => $this->hydrateEntry($data), $results);
    }

    public function count(TenantId $tenantId, array $filters = []): int
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('COUNT(*)')
            ->from('ext_translations')
            ->where("object_class = 'App\\\\Translation\\\\CustomTranslation'")
            ->andWhere('foreign_key LIKE :tenant_prefix')
            ->setParameter('tenant_prefix', $tenantId->toString().':%');

        $this->applyFilters($qb, $filters);

        return (int) $qb->executeQuery()->fetchOne();
    }

    public function findMissingInLocale(
        TenantId $tenantId,
        LanguageCode $sourceLocale,
        LanguageCode $targetLocale,
        ?TranslationDomain $domain = null,
    ): array {
        $sql = "
            SELECT DISTINCT
                SUBSTRING(source.foreign_key FROM POSITION(':' IN source.foreign_key) + 1) as translation_key,
                source.field as domain
            FROM ext_translations source
            LEFT JOIN ext_translations target ON
                target.foreign_key = source.foreign_key
                AND target.field = source.field
                AND target.locale = :target_locale
            WHERE source.object_class = 'App\\\\Translation\\\\CustomTranslation'
                AND source.foreign_key LIKE :tenant_prefix
                AND source.locale = :source_locale
                AND target.id IS NULL
        ";

        $params = [
            'tenant_prefix' => $tenantId->toString().':%',
            'source_locale' => $sourceLocale->value(),
            'target_locale' => $targetLocale->value(),
        ];

        if (null !== $domain) {
            $sql .= ' AND source.field = :domain';
            $params['domain'] = $domain->value();
        }

        $sql .= ' ORDER BY source.field, translation_key';

        $results = $this->connection->fetchAllAssociative($sql, $params);

        return array_map(
            fn ($row) => [
                'key' => $row['translation_key'],
                'domain' => $row['domain'],
            ],
            $results
        );
    }

    public function getStatistics(TenantId $tenantId): array
    {
        // Get all unique keys per domain and locale
        $sql = "
            SELECT
                field as domain,
                locale,
                COUNT(DISTINCT foreign_key) as count
            FROM ext_translations
            WHERE object_class = 'App\\\\Translation\\\\CustomTranslation'
                AND foreign_key LIKE :tenant_prefix
            GROUP BY field, locale
            ORDER BY field, locale
        ";

        $results = $this->connection->fetchAllAssociative($sql, [
            'tenant_prefix' => $tenantId->toString().':%',
        ]);

        // Get total unique keys per domain (considering all locales)
        $totalSql = "
            SELECT
                field as domain,
                COUNT(DISTINCT foreign_key) as total_keys
            FROM ext_translations
            WHERE object_class = 'App\\\\Translation\\\\CustomTranslation'
                AND foreign_key LIKE :tenant_prefix
            GROUP BY field
        ";

        $totals = $this->connection->fetchAllAssociative($totalSql, [
            'tenant_prefix' => $tenantId->toString().':%',
        ]);

        $totalsByDomain = [];
        foreach ($totals as $row) {
            $totalsByDomain[$row['domain']] = (int) $row['total_keys'];
        }

        // Build statistics structure
        $statistics = [
            'byDomain' => [],
            'byLocale' => [],
            'overall' => [
                'totalKeys' => array_sum($totalsByDomain),
                'domains' => count($totalsByDomain),
            ],
        ];

        // Group by domain
        foreach ($results as $row) {
            $domain = $row['domain'];
            $locale = $row['locale'];
            $count = (int) $row['count'];
            $totalKeys = $totalsByDomain[$domain] ?? 0;
            $coverage = $totalKeys > 0 ? round(($count / $totalKeys) * 100, 2) : 0;

            if (!isset($statistics['byDomain'][$domain])) {
                $statistics['byDomain'][$domain] = [
                    'totalKeys' => $totalKeys,
                    'locales' => [],
                ];
            }

            $statistics['byDomain'][$domain]['locales'][$locale] = [
                'translatedKeys' => $count,
                'coverage' => $coverage,
            ];

            // Group by locale
            if (!isset($statistics['byLocale'][$locale])) {
                $statistics['byLocale'][$locale] = [
                    'totalKeys' => 0,
                    'translatedKeys' => 0,
                    'domains' => [],
                ];
            }

            $statistics['byLocale'][$locale]['domains'][$domain] = [
                'translatedKeys' => $count,
                'totalKeys' => $totalKeys,
                'coverage' => $coverage,
            ];
        }

        // Calculate overall stats per locale
        foreach ($statistics['byLocale'] as $locale => &$localeStats) {
            $totalTranslated = 0;
            $totalKeys = 0;

            foreach ($localeStats['domains'] as $domainData) {
                $totalTranslated += $domainData['translatedKeys'];
                $totalKeys += $domainData['totalKeys'];
            }

            $localeStats['totalKeys'] = $totalKeys;
            $localeStats['translatedKeys'] = $totalTranslated;
            $localeStats['coverage'] = $totalKeys > 0 ? round(($totalTranslated / $totalKeys) * 100, 2) : 0;
        }

        return $statistics;
    }

    /**
     * @param \Doctrine\DBAL\Query\QueryBuilder $qb
     * @param array<string, mixed>              $filters
     */
    private function applyFilters($qb, array $filters): void
    {
        if (isset($filters['locale'])) {
            $qb->andWhere('locale = :locale')
                ->setParameter('locale', $filters['locale']);
        }

        if (isset($filters['domain'])) {
            $qb->andWhere('field = :domain')
                ->setParameter('domain', $filters['domain']);
        }

        if (isset($filters['key'])) {
            $qb->andWhere('foreign_key LIKE :key')
                ->setParameter('key', '%:'.$filters['key'].'%');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrateEntry(array $data): TranslationEntry
    {
        [$tenantIdStr, $keyStr] = explode(':', $data['foreign_key'], 2);

        return new TranslationEntry(
            id: $data['id'],
            tenantId: TenantId::fromString($tenantIdStr),
            locale: LanguageCode::fromString($data['locale']),
            domain: TranslationDomain::fromString($data['field']),
            key: TranslationKey::fromString($keyStr),
            value: TranslationValue::fromString($data['content'] ?? ''),
            createdAt: null,
            updatedAt: null,
        );
    }
}
