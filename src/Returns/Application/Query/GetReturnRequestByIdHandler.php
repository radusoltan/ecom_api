<?php

declare(strict_types=1);

namespace App\Returns\Application\Query;

use App\Returns\Application\DTO\ReturnRequestDTO;
use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for GetReturnRequestById query.
 */
#[AsMessageHandler]
final readonly class GetReturnRequestByIdHandler
{
    public function __construct(
        private ReturnRequestRepositoryInterface $returnRequestRepository
    ) {
    }

    public function __invoke(GetReturnRequestById $query): ?ReturnRequestDTO
    {
        $returnRequest = $this->returnRequestRepository->findById(
            ReturnRequestId::fromString($query->returnRequestId)
        );

        if ($returnRequest === null) {
            return null;
        }

        return ReturnRequestDTO::fromDomain($returnRequest);
    }
}
