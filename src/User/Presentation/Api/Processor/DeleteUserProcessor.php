<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\User\Application\Command\DeleteUser\DeleteUser;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * API Platform processor for deleting users.
 *
 * Delegates to DeleteUser command handler.
 *
 * @implements ProcessorInterface<UserEntity, void>
 */
final readonly class DeleteUserProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param UserEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        // Extract user ID from UserEntity
        $command = new DeleteUser(
            userId: $data->getId()
        );

        // Dispatch command
        $this->messageBus->dispatch($command);
    }
}
