<?php

declare(strict_types=1);

namespace App\User\Application\Command\RequestPasswordReset;

use App\Shared\Domain\ValueObject\Email;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Infrastructure\Persistence\Doctrine\Entity\PasswordResetTokenEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Handler for RequestPasswordReset command.
 *
 * Security best practice: Always returns success, even if user not found.
 * This prevents email enumeration attacks.
 */
#[AsMessageHandler]
final readonly class RequestPasswordResetHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus
    ) {
    }

    public function __invoke(RequestPasswordReset $command): void
    {
        // Find user by email (silently fail if not found - security)
        $user = $this->userRepository->findByEmail(Email::fromString($command->email));

        if (null === $user) {
            // Don't reveal that email doesn't exist (security best practice)
            return;
        }

        // Generate secure random token (64 characters)
        $token = bin2hex(random_bytes(32));

        // Create token entity (expires in 1 hour)
        $tokenEntity = new PasswordResetTokenEntity(
            id: Uuid::v7()->toString(),
            userId: $user->id()->toString(),
            token: $token,
            expiresAt: (new \DateTimeImmutable())->modify('+1 hour'),
            createdAt: new \DateTimeImmutable()
        );

        // Save token
        $this->entityManager->persist($tokenEntity);
        $this->entityManager->flush();

        // Dispatch async message to send email
        $this->messageBus->dispatch(new SendPasswordResetEmail(
            email: $command->email,
            token: $token,
            expiresAt: $tokenEntity->getExpiresAt()
        ));
    }
}
