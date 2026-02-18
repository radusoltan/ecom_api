<?php

declare(strict_types=1);

namespace App\Returns\Application\Query;

use App\Order\Domain\Model\OrderId;
use App\Returns\Application\DTO\ReturnRequestDTO;
use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for GetReturnRequestsByOrderId query.
 */
#[AsMessageHandler]
final readonly class GetReturnRequestsByOrderIdHandler
{
    public function __construct(
        private ReturnRequestRepositoryInterface $returnRequestRepository,
    ) {
    }

    /**
     * @return array<ReturnRequestDTO>
     */
    public function __invoke(GetReturnRequestsByOrderId $query): array
    {
        $returnRequests = $this->returnRequestRepository->findByOrderId(
            OrderId::fromString($query->orderId)
        );

        return array_map(
            fn ($returnRequest) => ReturnRequestDTO::fromDomain($returnRequest),
            $returnRequests
        );
    }
}
