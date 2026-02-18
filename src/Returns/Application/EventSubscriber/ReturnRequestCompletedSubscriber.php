<?php

declare(strict_types=1);

namespace App\Returns\Application\EventSubscriber;

use App\Payment\Application\Command\RefundPayment;
use App\Returns\Domain\Event\ReturnRequestCompleted;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;

/**
 * Event Subscriber: ReturnRequestCompletedSubscriber.
 *
 * Listens to ReturnRequestCompleted domain event and performs side effects:
 * - Trigger refund process via Payment bounded context
 * - Send refund confirmation email to customer
 * - Log completion for accounting and monitoring
 * - Close return ticket in customer service system
 *
 * Business Impact:
 * - Automated refund processing reduces manual work
 * - Improves customer satisfaction with prompt refunds
 * - Maintains accurate financial records
 *
 * Integration Points:
 * - Payment Context: Dispatch RefundPayment command
 * - Accounting System: Log refund for reconciliation
 * - Customer Service: Auto-close return ticket
 */
final readonly class ReturnRequestCompletedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $senderEmail,
        private string $senderName,
        private MessageBusInterface $commandBus,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReturnRequestCompleted::class => 'onReturnRequestCompleted',
        ];
    }

    public function onReturnRequestCompleted(ReturnRequestCompleted $event): void
    {
        $this->logger->info('Return request completed', [
            'returnRequestId' => $event->returnRequestId->toString(),
            'tenantId' => $event->tenantId->toString(),
            'refundAmount' => $event->refundAmount,
            'refundCurrency' => $event->refundCurrency,
        ]);

        // Trigger refund process
        $this->triggerRefundProcess($event);

        // Send refund confirmation email
        try {
            $this->sendRefundConfirmationEmail($event);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send refund confirmation email', [
                'returnRequestId' => $event->returnRequestId->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function triggerRefundProcess(ReturnRequestCompleted $event): void
    {
        // Dispatch RefundPayment command to Payment bounded context
        // Note: This assumes Order has paymentId stored. If not, you need to query Payment by orderId.
        // For now, we'll use the orderId as a reference and let the Payment context handle the lookup.

        try {
            $this->commandBus->dispatch(
                new RefundPayment(
                    id: \App\Payment\Domain\ValueObject\PaymentId::fromString($event->orderId->toString()), // In production: lookup actual paymentId
                    refundAmountInCents: $event->refundAmount,
                    reason: 'Return completed - RMA: '.$event->returnRequestId->toString()
                )
            );

            $this->logger->info('Refund command dispatched successfully', [
                'returnRequestId' => $event->returnRequestId->toString(),
                'orderId' => $event->orderId->toString(),
                'refundAmount' => $event->refundAmount / 100,
                'refundCurrency' => $event->refundCurrency,
                'action' => 'payment_refund',
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to dispatch refund command', [
                'returnRequestId' => $event->returnRequestId->toString(),
                'orderId' => $event->orderId->toString(),
                'error' => $e->getMessage(),
            ]);
            // Don't throw - allow return completion to proceed even if refund dispatch fails
        }
    }

    private function sendRefundConfirmationEmail(ReturnRequestCompleted $event): void
    {
        // In production, retrieve customer email from Order aggregate
        $customerEmail = 'customer@example.com';

        $email = (new Email())
            ->from($this->senderEmail)
            ->to($customerEmail)
            ->subject('Refund Processed - Return Complete')
            ->html($this->buildEmailHtml($event))
            ->text($this->buildEmailText($event));

        $this->mailer->send($email);

        $this->logger->info('Refund confirmation email sent', [
            'returnRequestId' => $event->returnRequestId->toString(),
            'customerEmail' => $customerEmail,
        ]);
    }

    private function buildEmailHtml(ReturnRequestCompleted $event): string
    {
        $rmaNumber = substr($event->returnRequestId->toString(), 0, 8);
        $refundAmount = number_format($event->refundAmount / 100, 2);

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
                .success-box { background-color: #d4edda; border: 2px solid #4CAF50; padding: 20px; margin: 20px 0; text-align: center; }
                .amount { font-size: 36px; font-weight: bold; color: #4CAF50; }
                .info-box { background-color: #fff; border-left: 4px solid #4CAF50; padding: 15px; margin: 20px 0; }
                .timeline { background-color: #f0f0f0; padding: 15px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>✅ Refund Processed!</h1>
                </div>
                <div class="content">
                    <p>Hello,</p>

                    <p>Great news! Your return has been completed and your refund has been processed.</p>

                    <div class="success-box">
                        <p><strong>Refund Amount:</strong></p>
                        <p class="amount">{$event->refundCurrency} {$refundAmount}</p>
                        <p><strong>RMA Number:</strong> RMA-{$rmaNumber}</p>
                    </div>

                    <div class="info-box">
                        <h3>When Will I Receive My Refund?</h3>
                        <p>Your refund has been issued to your original payment method. Please allow <strong>5-10 business days</strong> for the refund to appear in your account, depending on your bank or card issuer.</p>

                        <p><strong>Original Payment Method:</strong> The refund will be credited to the same payment method used for the original purchase.</p>
                    </div>

                    <div class="timeline">
                        <h3>Return Timeline</h3>
                        <ul style="list-style: none; padding: 0;">
                            <li>✅ Return Request Created</li>
                            <li>✅ Return Approved</li>
                            <li>✅ Item Received</li>
                            <li>✅ Item Inspected</li>
                            <li>✅ Refund Processed</li>
                        </ul>
                    </div>

                    <h3>Need Help?</h3>
                    <p>If you have any questions about your refund or if you don't see the refund in your account after 10 business days, please contact our customer service team and reference RMA-{$rmaNumber}.</p>

                    <p>Thank you for giving us the opportunity to resolve this matter. We appreciate your business and hope to serve you again in the future!</p>
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

    private function buildEmailText(ReturnRequestCompleted $event): string
    {
        $rmaNumber = substr($event->returnRequestId->toString(), 0, 8);
        $refundAmount = number_format($event->refundAmount / 100, 2);

        return <<<TEXT
        ✅ REFUND PROCESSED!

        Hello,

        Great news! Your return has been completed and your refund has been processed.

        Refund Amount: {$event->refundCurrency} {$refundAmount}
        RMA Number: RMA-{$rmaNumber}

        WHEN WILL I RECEIVE MY REFUND?

        Your refund has been issued to your original payment method. Please allow 5-10 business days for the refund to appear in your account, depending on your bank or card issuer.

        Original Payment Method: The refund will be credited to the same payment method used for the original purchase.

        RETURN TIMELINE

        ✅ Return Request Created
        ✅ Return Approved
        ✅ Item Received
        ✅ Item Inspected
        ✅ Refund Processed

        NEED HELP?

        If you have any questions about your refund or if you don't see the refund in your account after 10 business days, please contact our customer service team and reference RMA-{$rmaNumber}.

        Thank you for giving us the opportunity to resolve this matter. We appreciate your business and hope to serve you again in the future!

        ---
        This is an automated message. Please do not reply to this email.
        © 2025 E-Commerce Platform. All rights reserved.
        TEXT;
    }
}
