<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Application\DTO\OptionDTO;
use App\Catalog\Application\DTO\OptionValueDTO;
use App\Catalog\Domain\Repository\ConfigurableProductRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for GetProductOptions query.
 */
#[AsMessageHandler]
final readonly class GetProductOptionsHandler
{
    public function __construct(
        private ConfigurableProductRepositoryInterface $configurableProductRepository
    ) {
    }

    /**
     * @return OptionDTO[]
     */
    public function __invoke(GetProductOptions $query): array
    {
        $configurableProduct = $this->configurableProductRepository->findByProductId(
            $query->productId,
            $query->tenantId
        );

        if (!$configurableProduct) {
            return [];
        }

        $optionDTOs = [];

        foreach ($configurableProduct->getOptions() as $option) {
            $valueDTOs = [];

            foreach ($option->getValues() as $value) {
                $valueDTOs[] = new OptionValueDTO(
                    id: $value->getId()->toString(),
                    code: $value->getCode()->toString(),
                    nameTranslations: $value->getNameTranslations()->toArray(),
                    position: $value->getPosition()
                );
            }

            $optionDTOs[] = new OptionDTO(
                id: $option->getId()->toString(),
                code: $option->getCode()->toString(),
                nameTranslations: $option->getNameTranslations()->toArray(),
                position: $option->getPosition(),
                values: $valueDTOs
            );
        }

        return $optionDTOs;
    }
}
