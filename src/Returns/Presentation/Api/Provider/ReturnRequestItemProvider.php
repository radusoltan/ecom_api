<?php

declare(strict_types=1);

namespace App\Returns\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Returns\Application\Query\GetReturnRequestById;
use App\Returns\Presentation\Api\Transformer\ReturnRequestResourceTransformer;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Provider for retrieving a single return request.
 */
final class ReturnRequestItemProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $queryBus
    ) {
        $this->messageBus = $queryBus;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        $dto = $this->handle(new GetReturnRequestById($uriVariables['id']));

        if (null === $dto) {
            return null;
        }

        return ReturnRequestResourceTransformer::fromDTO($dto);
    }
}
