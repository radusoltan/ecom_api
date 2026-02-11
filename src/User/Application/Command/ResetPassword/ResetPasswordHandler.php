<?php

declare(strict_types=1);

namespace App\User\Application\Command\ResetPassword;

use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\UserId;
use App\User\Infrastructure\Persistence\Doctrine\Entity\PasswordResetTokenEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\RefreshTokenEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for ResetPassword command.
 *
 * Security measures:
 * - Validates token exists and not expired
 * - Validates token not already used
 * - Invalidates all refresh tokens after password change
 */
#[AsMessageHandler]
final readonly class ResetPasswordHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function __invoke(ResetPassword $command): void
    {
        // Find token
        $tokenEntity = $this->entityManager->getRepository(PasswordResetTokenEntity::class)
            ->findOneBy(['token' => $command->token]);

        if (null === $tokenEntity) {
            throw new \DomainException('Invalid or expired reset token');
        }

        // Validate token not expired
        if ($tokenEntity->isExpired()) {
            throw new \DomainException('Reset token has expired');
        }

        // Validate token not already used
        if ($tokenEntity->isUsed()) {
            throw new \DomainException('Reset token has already been used');
        }

        // Find user
        $user = $this->userRepository->findById(UserId::fromString($tokenEntity->getUserId()));

        if (null === $user) {
            throw new \DomainException('User not found');
        }

        // Update password
        $user->changePassword(HashedPassword::fromPlainPassword($command->newPassword));

        // Save user
        $this->userRepository->save($user);

        // Mark token as used
        $tokenEntity->markAsUsed();
        $this->entityManager->flush();

        // Invalidate all refresh tokens for this user (security)
        $this->invalidateAllUserRefreshTokens($user->email()->toString());
    }

    private function invalidateAllUserRefreshTokens(string $userEmail): void
    {
        // Delete all refresh tokens for this user using DQL
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(RefreshTokenEntity::class, 'rt')
            ->where('rt.username = :username')
            ->setParameter('username', $userEmail)
            ->getQuery()
            ->execute();
    }
}
