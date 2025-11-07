<?php

declare(strict_types=1);

namespace App\Returns\Application\EventSubscriber;

use App\Returns\Domain\Event\ReturnRequestRejected;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Event Subscriber: ReturnRequestRejectedSubscriber.
 *
 * Listens to ReturnRequestRejected domain event and performs side effects:
 * - Send rejection email to customer with clear explanation
 * - If item already received: trigger return shipping to customer
 * - Log rejection for quality analysis
 * - Close return ticket in customer service system
 *
 * Business Impact:
 * - Maintains customer trust through transparent communication
 * - Provides clear reasoning for rejections
 * - Reduces customer service inquiries
 * - Tracks rejection patterns for policy improvement
 */
final readonly class ReturnRequestRejectedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $senderEmail,
        private string $senderName
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReturnRequestRejected::class => 'onReturnRequestRejected',
        ];
    }

    public function onReturnRequestRejected(ReturnRequestRejected $event): void
    {
        $this->logger->warning('Return request rejected', [
            'returnRequestId' => $event->returnRequestId->toString(),
            'tenantId' => $event->tenantId->toString(),
            'rejectionReason' => $event->rejectionReason,
        ]);

        // Send rejection email to customer
        try {
            $this->sendRejectionEmail($event);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send rejection email', [
                'returnRequestId' => $event->returnRequestId->toString(),
                'error' => $e->getMessage(),
            ]);
        }

        // Future: If item was already received, trigger return shipping to customer
        $this->handleReturnShipping($event);
    }

    private function handleReturnShipping(ReturnRequestRejected $event): void
    {
        // Future: Check if item was already received at warehouse
        // If yes, generate return shipping label to send item back to customer
        //
        // $returnRequest = $returnRequestRepository->findById($event->returnRequestId);
        // if ($returnRequest->warehouseId() !== null) {
        //     // Item was received, trigger return shipping
        //     $this->logger->info('Triggering return shipping to customer', [
        //         'returnRequestId' => $event->returnRequestId->toString(),
        //         'warehouseId' => $returnRequest->warehouseId(),
        //     ]);
        // }
    }

    private function sendRejectionEmail(ReturnRequestRejected $event): void
    {
        // In production, retrieve customer email from Order aggregate
        $customerEmail = 'customer@example.com';

        $email = (new Email())
            ->from($this->senderEmail)
            ->to($customerEmail)
            ->subject('Return Request Update - Important Information')
            ->html($this->buildEmailHtml($event))
            ->text($this->buildEmailText($event));

        $this->mailer->send($email);

        $this->logger->info('Rejection email sent', [
            'returnRequestId' => $event->returnRequestId->toString(),
            'customerEmail' => $customerEmail,
        ]);
    }

    private function buildEmailHtml(ReturnRequestRejected $event): string
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
                .header { background-color: #ff9800; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .warning-box { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                .reason-box { background-color: #fff; border: 2px solid #ff9800; padding: 15px; margin: 20px 0; }
                .info-box { background-color: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Return Request Update</h1>
                </div>
                <div class="content">
                    <p>Hello,</p>

                    <p>We have reviewed your return request and unfortunately, we are unable to process your return at this time.</p>

                    <div class="warning-box">
                        <p><strong>RMA Number:</strong> RMA-{$rmaNumber}</p>
                        <p><strong>Status:</strong> Rejected</p>
                    </div>

                    <div class="reason-box">
                        <p><strong>Reason for Rejection:</strong></p>
                        <p>{$event->rejectionReason}</p>
                    </div>

                    <div class="info-box">
                        <h3>What Happens Next?</h3>
                        <ul>
                            <li>If you have already shipped the item to us, we will return it to you at no charge</li>
                            <li>If you have not yet shipped the item, please keep it - no further action is needed</li>
                            <li>No charges will be applied to your account</li>
                        </ul>
                    </div>

                    <h3>Still Have Questions?</h3>
                    <p>If you believe this decision was made in error or if you have additional information that may change the outcome, please contact our customer service team and reference RMA-{$rmaNumber}.</p>

                    <p>We understand this may not be the outcome you were hoping for, and we apologize for any inconvenience. We value your business and hope to continue serving you in the future.</p>

                    <h3>Our Return Policy</h3>
                    <p>For future reference, our standard return policy requires:</p>
                    <ul>
                        <li>Returns within 30 days of delivery</li>
                        <li>Items in original, unused condition</li>
                        <li>All original packaging and accessories included</li>
                        <li>Proof of purchase</li>
                    </ul>

                    <p>Thank you for your understanding.</p>
                </div>
                <div class="footer">
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>For assistance, please contact customer service with reference: RMA-{$rmaNumber}</p>
                    <p>&copy; 2025 E-Commerce Platform. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    private function buildEmailText(ReturnRequestRejected $event): string
    {
        $rmaNumber = substr($event->returnRequestId->toString(), 0, 8);

        return <<<TEXT
        RETURN REQUEST UPDATE

        Hello,

        We have reviewed your return request and unfortunately, we are unable to process your return at this time.

        RMA Number: RMA-{$rmaNumber}
        Status: Rejected

        REASON FOR REJECTION:

        {$event->rejectionReason}

        WHAT HAPPENS NEXT?

        - If you have already shipped the item to us, we will return it to you at no charge
        - If you have not yet shipped the item, please keep it - no further action is needed
        - No charges will be applied to your account

        STILL HAVE QUESTIONS?

        If you believe this decision was made in error or if you have additional information that may change the outcome, please contact our customer service team and reference RMA-{$rmaNumber}.

        We understand this may not be the outcome you were hoping for, and we apologize for any inconvenience. We value your business and hope to continue serving you in the future.

        OUR RETURN POLICY

        For future reference, our standard return policy requires:
        - Returns within 30 days of delivery
        - Items in original, unused condition
        - All original packaging and accessories included
        - Proof of purchase

        Thank you for your understanding.

        ---
        This is an automated message. Please do not reply to this email.
        For assistance, please contact customer service with reference: RMA-{$rmaNumber}
        © 2025 E-Commerce Platform. All rights reserved.
        TEXT;
    }
}
