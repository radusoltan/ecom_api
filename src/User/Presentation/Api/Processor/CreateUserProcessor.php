<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\User\Application\Command\CreateUser\CreateUser;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * API Platform processor for creating users.
 *
 * Delegates to CreateUser command handler.
 *
 * @implements ProcessorInterface<UserEntity, UserEntity>
 */
final readonly class CreateUserProcessor implements ProcessorInterface
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
        $command = new CreateUser(
            email: $data->getEmail(),
            username: $data->getUsername(),
            plainPassword: $data->getPassword(),
            roles: array_values(array_filter($data->getRoles(), fn (string $role) => 'ROLE_USER' !== $role))
        );

        // Dispatch command
        $this->messageBus->dispatch($command);

        // Return the created entity (API Platform will serialize it)
        return $data;
    }
}
