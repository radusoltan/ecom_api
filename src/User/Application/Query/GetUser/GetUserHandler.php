<?php

declare(strict_types=1);

namespace App\User\Application\Query\GetUser;

use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function __invoke(GetUser $query): User
    {
        $user = $this->userRepository->findById(UserId::fromString($query->userId));

        if (null === $user) {
            throw new \DomainException(sprintf('User with ID "%s" not found', $query->userId));
        }

        return $user;
    }
}
