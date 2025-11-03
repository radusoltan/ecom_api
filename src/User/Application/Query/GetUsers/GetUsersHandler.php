<?php

declare(strict_types=1);

namespace App\User\Application\Query\GetUsers;

use App\User\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetUsersHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * @return list<\App\User\Domain\Model\User>
     */
    public function __invoke(GetUsers $query): array
    {
        return $this->userRepository->findAll();
    }
}
