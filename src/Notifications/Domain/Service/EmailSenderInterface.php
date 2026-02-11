<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Service;

/**
 * Interface for email sending service.
 *
 * This abstraction allows for different email implementations
 * and makes testing easier by allowing mocking.
 */
interface EmailSenderInterface
{
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
     * @throws \Throwable If email sending fails
     */
    public function send(
        string $to,
        string $subject,
        string $templateName,
        array $context = [],
        ?string $locale = null,
        ?string $fromEmail = null,
    ): void;
}
