<?php

declare(strict_types=1);

namespace App\Returns\Application\EventSubscriber;

use App\Returns\Domain\Event\ReturnRequestInspected;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Event Subscriber: ReturnRequestInspectedSubscriber.
 *
 * Listens to ReturnRequestInspected domain event and performs side effects:
 * - If item is resellable: trigger inventory adjustment (return to stock)
 * - If item is not resellable: log for disposal workflow
 * - Send inspection result email to customer
 * - Log inspection for quality tracking
 *
 * Business Impact:
 * - Maximizes inventory recovery for resellable items
 * - Maintains customer transparency
 * - Provides data for product quality analysis
 *
 * Integration Points:
 * - Inventory Context: Dispatch AdjustStock command for resellable items
 * - Quality Assurance: Track non-resellable items for product improvement
 */
final readonly class ReturnRequestInspectedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $senderEmail,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReturnRequestInspected::class => 'onReturnRequestInspected',
        ];
    }

    public function onReturnRequestInspected(ReturnRequestInspected $event): void
    {
        $this->logger->info('Return request inspected', [
            'returnRequestId' => $event->returnRequestId->toString(),
            'tenantId' => $event->tenantId->toString(),
            'isResellable' => $event->isResellable,
            'inspectionNotes' => $event->inspectionNotes,
        ]);

        // Handle inventory restocking for resellable items
        if ($event->isResellable) {
            $this->handleResellableItem($event);
        } else {
            $this->handleNonResellableItem($event);
        }

        // Send inspection result email to customer
        try {
            $this->sendInspectionResultEmail($event);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send inspection result email', [
                'returnRequestId' => $event->returnRequestId->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handleResellableItem(ReturnRequestInspected $event): void
    {
        // TODO: Dispatch AdjustStockCommand once ReturnRequest carries productId + quantity.
        // ReturnRequest currently only stores orderId — the Order aggregate must be queried
        // to resolve product and quantity for inventory restocking.
        $this->logger->info('Resellable item identified — stock adjustment pending implementation', [
            'returnRequestId' => $event->returnRequestId->toString(),
            'tenantId' => $event->tenantId->toString(),
            'action' => 'return_to_inventory',
        ]);
    }

    private function handleNonResellableItem(ReturnRequestInspected $event): void
    {
        // Future: Trigger disposal workflow or quality assurance review
        $this->logger->warning('Non-resellable item - quality assurance review needed', [
            'returnRequestId' => $event->returnRequestId->toString(),
            'inspectionNotes' => $event->inspectionNotes,
            'action' => 'quality_review',
        ]);
    }

    private function sendInspectionResultEmail(ReturnRequestInspected $event): void
    {
        // In production, retrieve customer email from Order aggregate
        $customerEmail = 'customer@example.com';

        $email = (new Email())
            ->from($this->senderEmail)
            ->to($customerEmail)
            ->subject('Return Inspection Complete - Refund Processing')
            ->html($this->buildEmailHtml($event))
            ->text($this->buildEmailText($event));

        $this->mailer->send($email);

        $this->logger->info('Inspection result email sent', [
            'returnRequestId' => $event->returnRequestId->toString(),
            'customerEmail' => $customerEmail,
        ]);
    }

    private function buildEmailHtml(ReturnRequestInspected $event): string
    {
        $rmaNumber = substr($event->returnRequestId->toString(), 0, 8);
        $statusBadge = $event->isResellable
            ? '<span style="color: #4CAF50; font-weight: bold;">✅ Approved for Full Refund</span>'
            : '<span style="color: #ff9800; font-weight: bold;">⚠️ Partial Refund</span>';

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #2196F3; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .status-box { background-color: #fff; border: 2px solid #2196F3; padding: 15px; margin: 20px 0; text-align: center; }
                .info-box { background-color: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Inspection Complete</h1>
                </div>
                <div class="content">
                    <p>Hello,</p>

                    <p>We have completed the inspection of your returned item.</p>

                    <div class="status-box">
                        <p><strong>RMA Number:</strong> RMA-{$rmaNumber}</p>
                        <p><strong>Inspection Result:</strong><br>{$statusBadge}</p>
                    </div>

                    <div class="info-box">
                        <p><strong>Inspection Notes:</strong></p>
                        <p>{$event->inspectionNotes}</p>
                    </div>

                    <h2>Next Steps</h2>
                    <p>Your refund is now being processed and will be completed within 5-10 business days.</p>

                    <ul>
                        <li>Refund will be issued to your original payment method</li>
                        <li>You will receive a confirmation email once the refund is processed</li>
                        <li>Please allow 5-10 business days for the refund to appear in your account</li>
                    </ul>

                    <p>Thank you for your patience throughout this process.</p>

                    <p>If you have any questions, please contact our customer service team and reference RMA-{$rmaNumber}.</p>
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

    private function buildEmailText(ReturnRequestInspected $event): string
    {
        $rmaNumber = substr($event->returnRequestId->toString(), 0, 8);
        $status = $event->isResellable ? '✅ Approved for Full Refund' : '⚠️ Partial Refund';

        return <<<TEXT
        INSPECTION COMPLETE

        Hello,

        We have completed the inspection of your returned item.

        RMA Number: RMA-{$rmaNumber}
        Inspection Result: {$status}

        Inspection Notes:
        {$event->inspectionNotes}

        NEXT STEPS

        Your refund is now being processed and will be completed within 5-10 business days.

        - Refund will be issued to your original payment method
        - You will receive a confirmation email once the refund is processed
        - Please allow 5-10 business days for the refund to appear in your account

        Thank you for your patience throughout this process.

        If you have any questions, please contact our customer service team and reference RMA-{$rmaNumber}.

        ---
        This is an automated message. Please do not reply to this email.
        © 2025 E-Commerce Platform. All rights reserved.
        TEXT;
    }
}
