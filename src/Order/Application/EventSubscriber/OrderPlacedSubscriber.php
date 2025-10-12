<?php

declare(strict_types=1);

namespace App\Order\Application\EventSubscriber;

use App\Order\Domain\Event\OrderPlaced;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Handles OrderPlaced domain events by sending confirmation emails to customers
 *
 * Business Rules:
 * - Send confirmation email immediately after order placement
 * - Email includes order ID, total amount, and customer details
 * - Failures should be logged but not block order placement
 */
final readonly class OrderPlacedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $senderEmail = 'orders@ecommerce.local',
        private string $senderName = 'E-Commerce Platform'
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderPlaced::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(OrderPlaced $event): void
    {
        try {
            $this->sendConfirmationEmail($event);

            $this->logger->info('Order confirmation email sent', [
                'orderId' => $event->orderId->toString(),
                'customerEmail' => $event->customerEmail,
                'tenantId' => $event->tenantId->toString(),
            ]);
        } catch (\Throwable $exception) {
            // Log error but don't throw - email failure shouldn't block order placement
            $this->logger->error('Failed to send order confirmation email', [
                'orderId' => $event->orderId->toString(),
                'customerEmail' => $event->customerEmail,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    private function sendConfirmationEmail(OrderPlaced $event): void
    {
        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
            ->to($event->customerEmail)
            ->subject(sprintf('Order Confirmation - %s', $event->orderId->toString()))
            ->html($this->buildEmailBody($event))
            ->text($this->buildEmailTextBody($event));

        $this->mailer->send($email);
    }

    private function buildEmailBody(OrderPlaced $event): string
    {
        $orderId = $event->orderId->toString();
        $total = $event->total->getAmount() / 100; // Convert cents to currency units
        $currency = $event->total->getCurrency()->getCurrencyCode();

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; margin: 20px 0; }
                .order-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #4CAF50; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>✓ Order Confirmed</h1>
                </div>
                <div class="content">
                    <p>Dear Customer,</p>
                    <p>Thank you for your order! We've received your order and will begin processing it shortly.</p>

                    <div class="order-details">
                        <h3>Order Details</h3>
                        <p><strong>Order ID:</strong> {$orderId}</p>
                        <p><strong>Total Amount:</strong> {$total} {$currency}</p>
                        <p><strong>Status:</strong> Pending</p>
                    </div>

                    <p>You will receive another email when your order status changes.</p>
                    <p>If you have any questions, please don't hesitate to contact our support team.</p>
                </div>
                <div class="footer">
                    <p>This is an automated message, please do not reply.</p>
                    <p>&copy; 2025 E-Commerce Platform. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    private function buildEmailTextBody(OrderPlaced $event): string
    {
        $orderId = $event->orderId->toString();
        $total = $event->total->getAmount() / 100;
        $currency = $event->total->getCurrency()->getCurrencyCode();

        return <<<TEXT
        ORDER CONFIRMED

        Dear Customer,

        Thank you for your order! We've received your order and will begin processing it shortly.

        Order Details:
        - Order ID: {$orderId}
        - Total Amount: {$total} {$currency}
        - Status: Pending

        You will receive another email when your order status changes.

        If you have any questions, please don't hesitate to contact our support team.

        ---
        This is an automated message, please do not reply.
        © 2025 E-Commerce Platform. All rights reserved.
        TEXT;
    }
}
