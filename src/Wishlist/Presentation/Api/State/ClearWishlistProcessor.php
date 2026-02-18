<?php

declare(strict_types=1);

namespace App\Wishlist\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Wishlist\Application\Command\ClearWishlist;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ClearWishlistProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $tenantId = $request->headers->get('X-Tenant-ID');
        $customerId = $request->headers->get('X-Customer-ID') ?? 'guest'; // TODO: Get from auth

        if (!$tenantId) {
            throw new \RuntimeException('X-Tenant-ID header is required');
        }

        $command = new ClearWishlist(
            $customerId,
            TenantId::fromString($tenantId)
        );

        $this->commandBus->dispatch($command);
    }
}
