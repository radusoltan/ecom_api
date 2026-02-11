<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Email;

use App\Notifications\Domain\Service\EmailSenderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Email sender service using Symfony Mailer.
 *
 * This service provides a unified interface for sending templated emails
 * with HTML and plain text versions.
 *
 * Features:
 * - Automatic HTML and text template rendering
 * - Translation support via Twig
 * - Error logging and retry mechanism via Messenger
 * - Configurable sender address
 */
final readonly class SymfonyEmailSender implements EmailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger,
        private string $fromEmail,
    ) {
    }

    /**
     * Send an email using HTML and text templates.
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $templateName Template name without extension (e.g., 'order/order_placed')
     * @param array<string, mixed> $context Template variables
     * @param string|null $locale Optional locale for translations (default: 'en')
     * @param string|null $fromEmail Optional custom sender email
     *
     * @throws TransportExceptionInterface If email sending fails
     */
    public function send(
        string $to,
        string $subject,
        string $templateName,
        array $context = [],
        ?string $locale = null,
        ?string $fromEmail = null,
    ): void {
        // Add locale to context for template rendering
        $context['locale'] = $locale ?? 'en';

        // Create templated email
        $email = (new TemplatedEmail())
            ->from($fromEmail ?? $this->fromEmail)
            ->to($to)
            ->subject($subject)
            ->htmlTemplate("emails/{$templateName}.html.twig")
            ->context($context);

        // Add text template if it exists
        try {
            if ($this->twig->getLoader()->exists("emails/{$templateName}.txt.twig")) {
                $email->textTemplate("emails/{$templateName}.txt.twig");
            }
        } catch (\Throwable $e) {
            // Log warning but continue - text template is optional
            $this->logger->warning('Text email template not found', [
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);
        }

        // Send email with error handling
        try {
            $this->mailer->send($email);

            $this->logger->info('Email sent successfully', [
                'to' => $to,
                'subject' => $subject,
                'template' => $templateName,
                'locale' => $context['locale'],
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send email', [
                'to' => $to,
                'subject' => $subject,
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger Messenger retry mechanism
            throw $e;
        }
    }

    /**
     * Send multiple emails in batch.
     *
     * Useful for bulk notifications (e.g., newsletter, promotional emails).
     *
     * @param array<string> $recipients Array of recipient email addresses
     * @param string $subject Email subject
     * @param string $templateName Template name without extension
     * @param array<string, mixed> $context Template variables
     * @param string|null $locale Optional locale for translations
     *
     * @return array{sent: int, failed: int, errors: array<string, string>}
     */
    public function sendBatch(
        array $recipients,
        string $subject,
        string $templateName,
        array $context = [],
        ?string $locale = null,
    ): array {
        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($recipients as $recipient) {
            try {
                $this->send($recipient, $subject, $templateName, $context, $locale);
                ++$sent;
            } catch (TransportExceptionInterface $e) {
                ++$failed;
                $errors[$recipient] = $e->getMessage();

                $this->logger->error('Batch email failed for recipient', [
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Batch email send completed', [
            'total' => count($recipients),
            'sent' => $sent,
            'failed' => $failed,
        ]);

        return [
            'sent' => $sent,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }
}
