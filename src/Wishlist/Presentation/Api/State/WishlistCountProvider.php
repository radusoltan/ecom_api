<?php

declare(strict_types=1);

namespace App\Wishlist\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Wishlist\Application\Query\GetWishlistItemCount;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class WishlistCountProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
        private readonly RequestStack $requestStack,
    ) {
        $this->messageBus = $queryBus;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $tenantId = $request->headers->get('X-Tenant-ID');
        $customerId = $request->headers->get('X-Customer-ID') ?? 'guest'; // TODO: Get from auth

        if (!$tenantId) {
            throw new \RuntimeException('X-Tenant-ID header is required');
        }

        $query = new GetWishlistItemCount($customerId, TenantId::fromString($tenantId));
        $count = $this->handle($query);

        return [['count' => $count]];
    }
}
