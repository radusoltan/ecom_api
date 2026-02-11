<?php

declare(strict_types=1);

namespace App\User\Application\Command\RequestPasswordReset;

use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handler for SendPasswordResetEmail message.
 *
 * Sends password reset email asynchronously via Symfony Messenger.
 */
#[AsMessageHandler]
final readonly class SendPasswordResetEmailHandler
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private string $frontendUrl,
        private string $fromEmail
    ) {
    }

    public function __invoke(SendPasswordResetEmail $message): void
    {
        // Calculate expiration time in hours
        $now = new \DateTimeImmutable();
        $expiresInHours = (int) ceil(($message->expiresAt->getTimestamp() - $now->getTimestamp()) / 3600);

        // Build reset URL (frontend URL)
        $resetUrl = sprintf('%s/auth/reset-password?token=%s', rtrim($this->frontendUrl, '/'), $message->token);

        // Create email
        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to($message->email)
            ->subject($this->translator->trans('customer.password_reset.subject', [], 'emails'))
            ->htmlTemplate('emails/password_reset.html.twig')
            ->textTemplate('emails/password_reset.txt.twig')
            ->context([
                'resetUrl' => $resetUrl,
                'token' => $message->token,
                'expiresInHours' => $expiresInHours,
                'userEmail' => $message->email, // 'email' is reserved by TemplatedEmail
            ]);

        // Send email (with error handling - don't fail silently)
        try {
            $this->mailer->send($email);
            $this->logger->info('Password reset email sent', [
                'email' => $message->email,
                'expires_at' => $message->expiresAt->format('Y-m-d H:i:s'),
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send password reset email', [
                'email' => $message->email,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }
}
