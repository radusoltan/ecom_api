<?php

declare(strict_types=1);

namespace App\Returns\Application\EventSubscriber;

use App\Returns\Domain\Event\ReturnRequestCreated;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Event Subscriber: ReturnRequestCreatedSubscriber.
 *
 * Listens to ReturnRequestCreated domain event and performs side effects:
 * - Send RMA confirmation email to customer
 * - Log return request creation for monitoring
 *
 * Business Impact:
 * - Improves customer experience with immediate confirmation
 * - Provides RMA number for tracking
 * - Sets expectations for return process
 */
final readonly class ReturnRequestCreatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $senderEmail,
        private string $senderName,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReturnRequestCreated::class => 'onReturnRequestCreated',
        ];
    }

    public function onReturnRequestCreated(ReturnRequestCreated $event): void
    {
        $this->logger->info('Return request created', [
            'returnRequestId' => $event->returnRequestId->toString(),
            'tenantId' => $event->tenantId->toString(),
            'orderId' => $event->orderId,
            'reason' => $event->reason,
        ]);

        try {
            $this->sendRMAConfirmationEmail($event);
        } catch (TransportExceptionInterface $e) {
            // Email failures should not block return creation
            $this->logger->error('Failed to send RMA confirmation email', [
                'returnRequestId' => $event->returnRequestId->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendRMAConfirmationEmail(ReturnRequestCreated $event): void
    {
        // In production, retrieve customer email from Order aggregate
        // For now, using placeholder
        $customerEmail = 'customer@example.com';

        $email = (new Email())
            ->from($this->senderEmail)
            ->to($customerEmail)
            ->subject('Return Request Confirmed - RMA #'.substr($event->returnRequestId->toString(), 0, 8))
            ->html($this->buildEmailHtml($event))
            ->text($this->buildEmailText($event));

        $this->mailer->send($email);

        $this->logger->info('RMA confirmation email sent', [
            'returnRequestId' => $event->returnRequestId->toString(),
            'customerEmail' => $customerEmail,
        ]);
    }

    private function buildEmailHtml(ReturnRequestCreated $event): string
    {
        $rmaNumber = substr($event->returnRequestId->toString(), 0, 8);

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .rma-box { background-color: #fff; border: 2px solid #4CAF50; padding: 15px; margin: 20px 0; }
                .rma-number { font-size: 24px; font-weight: bold; color: #4CAF50; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Return Request Received</h1>
                </div>
                <div class="content">
                    <p>Hello,</p>

                    <p>We have received your return request and created an RMA (Return Merchandise Authorization) for your order.</p>

                    <div class="rma-box">
                        <p>Your RMA Number:</p>
                        <p class="rma-number">RMA-{$rmaNumber}</p>
                    </div>

                    <p><strong>Return Reason:</strong><br>
                    {$event->reason}</p>

                    <p><strong>Next Steps:</strong></p>
                    <ul>
                        <li>Our customer service team will review your request within 1-2 business days</li>
                        <li>If approved, you will receive a prepaid return shipping label via email</li>
                        <li>Please include the RMA number on the return package</li>
                        <li>Once we receive and inspect the item, we will process your refund</li>
                    </ul>

                    <p><strong>Tracking Your Return:</strong><br>
                    You can check the status of your return request at any time using your RMA number.</p>

                    <p>If you have any questions, please contact our customer service team and reference RMA-{$rmaNumber}.</p>

                    <p>Thank you for your patience.</p>
                </div>
                <div class="footer">
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; 2025 E-Commerce Platform. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    private function buildEmailText(ReturnRequestCreated $event): string
    {
        $rmaNumber = substr($event->returnRequestId->toString(), 0, 8);

        return <<<TEXT
        RETURN REQUEST RECEIVED

        Hello,

        We have received your return request and created an RMA (Return Merchandise Authorization) for your order.

        Your RMA Number: RMA-{$rmaNumber}

        Return Reason:
        {$event->reason}

        Next Steps:
        - Our customer service team will review your request within 1-2 business days
        - If approved, you will receive a prepaid return shipping label via email
        - Please include the RMA number on the return package
        - Once we receive and inspect the item, we will process your refund

        Tracking Your Return:
        You can check the status of your return request at any time using your RMA number.

        If you have any questions, please contact our customer service team and reference RMA-{$rmaNumber}.

        Thank you for your patience.

        ---
        This is an automated message. Please do not reply to this email.
        © 2025 E-Commerce Platform. All rights reserved.
        TEXT;
    }
}
