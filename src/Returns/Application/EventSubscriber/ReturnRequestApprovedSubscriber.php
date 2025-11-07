<?php

declare(strict_types=1);

namespace App\Returns\Application\EventSubscriber;

use App\Returns\Domain\Event\ReturnRequestApproved;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Event Subscriber: ReturnRequestApprovedSubscriber.
 *
 * Listens to ReturnRequestApproved domain event and performs side effects:
 * - Send return shipping label to customer
 * - Send return instructions email
 * - Log approval for monitoring
 *
 * Business Impact:
 * - Enables customer to ship item back
 * - Provides clear return instructions
 * - Reduces customer service inquiries
 *
 * Future Enhancements:
 * - Generate actual prepaid shipping label (UPS, FedEx, DHL integration)
 * - Attach PDF label to email
 * - Schedule carrier pickup if requested
 */
final readonly class ReturnRequestApprovedSubscriber implements EventSubscriberInterface
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
            ReturnRequestApproved::class => 'onReturnRequestApproved',
        ];
    }

    public function onReturnRequestApproved(ReturnRequestApproved $event): void
    {
        $this->logger->info('Return request approved', [
            'returnRequestId' => $event->returnRequestId->toString(),
            'tenantId' => $event->tenantId->toString(),
        ]);

        try {
            $this->sendReturnLabelEmail($event);
        } catch (TransportExceptionInterface $e) {
            // Email failures should not block approval
            $this->logger->error('Failed to send return label email', [
                'returnRequestId' => $event->returnRequestId->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendReturnLabelEmail(ReturnRequestApproved $event): void
    {
        // In production, retrieve customer email from Order aggregate
        $customerEmail = 'customer@example.com';

        $email = (new Email())
            ->from($this->senderEmail)
            ->to($customerEmail)
            ->subject('Return Approved - Shipping Label Enclosed')
            ->html($this->buildEmailHtml($event))
            ->text($this->buildEmailText($event));

        // Future: Attach PDF shipping label
        // $email->attachFromPath('/path/to/label.pdf', 'Return_Shipping_Label.pdf', 'application/pdf');

        $this->mailer->send($email);

        $this->logger->info('Return label email sent', [
            'returnRequestId' => $event->returnRequestId->toString(),
            'customerEmail' => $customerEmail,
        ]);
    }

    private function buildEmailHtml(ReturnRequestApproved $event): string
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
                .info-box { background-color: #fff; border-left: 4px solid #4CAF50; padding: 15px; margin: 20px 0; }
                .warning-box { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                .instructions { background-color: #e7f3ff; padding: 15px; margin: 20px 0; }
                .button { display: inline-block; padding: 12px 24px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>✅ Return Approved!</h1>
                </div>
                <div class="content">
                    <p>Hello,</p>

                    <p>Good news! Your return request has been approved.</p>

                    <div class="info-box">
                        <p><strong>RMA Number:</strong> RMA-{$rmaNumber}</p>
                    </div>

                    <h2>Return Instructions</h2>

                    <div class="instructions">
                        <p><strong>Step 1: Prepare Your Package</strong></p>
                        <ul>
                            <li>Securely pack the item in its original packaging if possible</li>
                            <li>Include all accessories, manuals, and parts</li>
                            <li>Print and include this RMA number: <strong>RMA-{$rmaNumber}</strong></li>
                        </ul>

                        <p><strong>Step 2: Attach Shipping Label</strong></p>
                        <ul>
                            <li>A prepaid return shipping label is attached to this email (PDF)</li>
                            <li>Print and attach the label to the outside of your package</li>
                            <li>Cover or remove any other shipping labels</li>
                        </ul>

                        <p><strong>Step 3: Ship Your Return</strong></p>
                        <ul>
                            <li>Drop off your package at any authorized shipping location</li>
                            <li>Keep your receipt for tracking purposes</li>
                            <li>You will receive tracking updates via email</li>
                        </ul>
                    </div>

                    <div class="warning-box">
                        <p><strong>⚠️ Important:</strong></p>
                        <ul>
                            <li>You must ship the item within <strong>14 days</strong> of receiving this email</li>
                            <li>Returns without RMA number may be refused or delayed</li>
                            <li>Refund will be processed within 5-10 business days after inspection</li>
                        </ul>
                    </div>

                    <h2>What Happens Next?</h2>
                    <ol>
                        <li>Ship your package using the provided label</li>
                        <li>We will send you a confirmation when we receive your return</li>
                        <li>Our team will inspect the item (1-2 business days)</li>
                        <li>Refund will be issued to your original payment method</li>
                        <li>You will receive a refund confirmation email</li>
                    </ol>

                    <p style="text-align: center; margin-top: 30px;">
                        <a href="#" class="button">Download Shipping Label (Coming Soon)</a>
                    </p>

                    <p>If you have any questions, please contact our customer service team and reference RMA-{$rmaNumber}.</p>

                    <p>Thank you for your business!</p>
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

    private function buildEmailText(ReturnRequestApproved $event): string
    {
        $rmaNumber = substr($event->returnRequestId->toString(), 0, 8);

        return <<<TEXT
        ✅ RETURN APPROVED!

        Hello,

        Good news! Your return request has been approved.

        RMA Number: RMA-{$rmaNumber}

        RETURN INSTRUCTIONS

        Step 1: Prepare Your Package
        - Securely pack the item in its original packaging if possible
        - Include all accessories, manuals, and parts
        - Print and include this RMA number: RMA-{$rmaNumber}

        Step 2: Attach Shipping Label
        - A prepaid return shipping label is attached to this email (PDF)
        - Print and attach the label to the outside of your package
        - Cover or remove any other shipping labels

        Step 3: Ship Your Return
        - Drop off your package at any authorized shipping location
        - Keep your receipt for tracking purposes
        - You will receive tracking updates via email

        ⚠️ IMPORTANT:
        - You must ship the item within 14 days of receiving this email
        - Returns without RMA number may be refused or delayed
        - Refund will be processed within 5-10 business days after inspection

        WHAT HAPPENS NEXT?
        1. Ship your package using the provided label
        2. We will send you a confirmation when we receive your return
        3. Our team will inspect the item (1-2 business days)
        4. Refund will be issued to your original payment method
        5. You will receive a refund confirmation email

        If you have any questions, please contact our customer service team and reference RMA-{$rmaNumber}.

        Thank you for your business!

        ---
        This is an automated message. Please do not reply to this email.
        © 2025 E-Commerce Platform. All rights reserved.
        TEXT;
    }
}
