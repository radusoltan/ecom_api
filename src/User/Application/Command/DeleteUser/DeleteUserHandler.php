<?php

declare(strict_types=1);

namespace App\User\Application\Command\DeleteUser;

use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function __invoke(DeleteUser $command): void
    {
        // Find user
        $user = $this->userRepository->findById(UserId::fromString($command->userId));
        if ($user === null) {
            throw new \DomainException(sprintf('User with ID "%s" not found', $command->userId));
        }

        // Delete user
        $this->userRepository->delete($user);
    }
}
