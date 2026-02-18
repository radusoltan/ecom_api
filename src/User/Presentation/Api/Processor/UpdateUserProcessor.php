<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\User\Application\Command\UpdateUser\UpdateUser;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * API Platform processor for updating users.
 *
 * Delegates to UpdateUser command handler.
 *
 * @implements ProcessorInterface<UserEntity, UserEntity>
 */
final readonly class UpdateUserProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param UserEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserEntity
    {
        // Extract data from UserEntity
        $command = new UpdateUser(
            userId: $data->getId(),
            username: $data->getUsername(),
            roles: array_values(array_filter($data->getRoles(), fn (string $role) => 'ROLE_USER' !== $role))
        );

        // Dispatch command
        $this->messageBus->dispatch($command);

        // Return the updated entity (API Platform will serialize it)
        return $data;
    }
}
