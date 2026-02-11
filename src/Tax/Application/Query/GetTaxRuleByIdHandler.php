<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

use App\Tax\Application\DTO\TaxRuleDto;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Tax Rule By ID Query Handler.
 *
 * Returns a single tax rule by ID, or null if not found.
 */
#[AsMessageHandler]
final readonly class GetTaxRuleByIdHandler
{
    public function __construct(
        private TaxRuleRepositoryInterface $taxRuleRepository
    ) {
    }

    public function __invoke(GetTaxRuleById $query): ?TaxRuleDto
    {
        $taxRule = $this->taxRuleRepository->findById($query->id);

        if (null === $taxRule) {
            return null;
        }

        return TaxRuleDto::fromDomainModel($taxRule);
    }
}
