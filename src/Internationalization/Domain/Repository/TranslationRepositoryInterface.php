<?php

declare(strict_types=1);

namespace App\Internationalization\Domain\Repository;

use App\Internationalization\Domain\Model\Translation;
use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationId;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Translation Repository Interface (Port) for Aggregate Root.
 *
 * Write-side contract for the Translation aggregate per PRD §4.7.
 */
interface TranslationRepositoryInterface
{
    public function save(Translation $translation): void;

    public function findById(TranslationId $id, TenantId $tenantId): ?Translation;

    public function findByKey(
        TenantId $tenantId,
        LanguageCode $locale,
        TranslationDomain $domain,
        TranslationKey $key,
    ): ?Translation;

    public function delete(TranslationId $id, TenantId $tenantId): void;
}
