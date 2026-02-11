# Payment Email Templates

Professional Twig email templates for Payment notifications.

## Templates

| Template | File | Description | Header Color |
|----------|------|-------------|--------------|
| **Payment Captured** | `payment_captured.html.twig` | Successful payment confirmation | Green (#10b981) |
| **Payment Failed** | `payment_failed.html.twig` | Payment failure notification | Red (#ef4444) |
| **Payment Refunded** | `payment_refunded.html.twig` | Refund processed notification | Blue (#3b82f6) |
| **Payment Cancelled** | `payment_cancelled.html.twig` | Payment cancellation notification | Orange (#f59e0b) |

## Template Variables

### Common Variables (All Templates)

| Variable | Type | Required | Description | Example |
|----------|------|----------|-------------|---------|
| `payment_id` | string | Yes | Unique payment identifier | `pay_1234567890` |
| `amount` | string | Yes | Formatted amount | `99.99` |
| `currency` | string | Yes | Currency code | `USD`, `EUR` |
| `customer_name` | string | Yes | Customer full name | `John Doe` |
| `customer_email` | string | Yes | Customer email address | `john@example.com` |
| `date` | DateTime | Yes | Payment date/time | DateTime object |
| `order_id` | string | Optional | Related order ID | `ORD-123456` |

### Template-Specific Variables

#### payment_captured.html.twig
- `orderViewUrl` (optional) - Link to view order details

#### payment_failed.html.twig
- `error_message` (optional) - Sanitized error message (max 200 chars)
- `retryPaymentUrl` (optional) - Link to retry payment

#### payment_refunded.html.twig
- `reason` (optional) - Refund reason
- `orderViewUrl` (optional) - Link to view order details

#### payment_cancelled.html.twig
- `reason` (optional) - Cancellation reason
- `restartOrderUrl` (optional) - Link to place new order

## Translation Keys Required

Add these keys to `translations/emails.{locale}.yaml`:

```yaml
emails:
  payment:
    # Common keys
    payment_details: "Payment Details"
    payment_id: "Payment ID"
    amount: "Amount"
    date: "Date"
    order_id: "Order ID"
    view_order: "View Order"

    # payment_captured.html.twig
    captured:
      title: "Payment Confirmation"
      header: "Payment Successful"
      greeting: "Dear %customer_name%"
      confirmation: "We have successfully received your payment. Thank you for your order!"
      next_steps: "What Happens Next"
      processing_info: "Your order is now being processed and will be shipped soon."
      receipt_info: "A receipt has been sent to %customer_email%."
      thank_you: "Thank you for choosing our store!"
      questions: "If you have any questions, please don't hesitate to contact our support team."

    # payment_failed.html.twig
    failed:
      title: "Payment Failed"
      header: "Payment Could Not Be Processed"
      greeting: "Dear %customer_name%"
      notification: "Unfortunately, we were unable to process your payment."
      error_reason: "Error Details"
      what_to_do: "What You Can Do"
      check_card_details: "Verify your card details are correct"
      check_balance: "Ensure you have sufficient funds"
      contact_bank: "Contact your bank or card issuer"
      try_different_method: "Try a different payment method"
      try_again: "Try Payment Again"
      apology: "We apologize for the inconvenience."
      support: "If you continue experiencing issues, please contact our support team for assistance."

    # payment_refunded.html.twig
    refunded:
      title: "Refund Processed"
      header: "Refund Confirmation"
      greeting: "Dear %customer_name%"
      confirmation: "Your refund has been processed successfully."
      refund_amount: "Refund Amount"
      refund_reason: "Refund Reason"
      processing_time: "Processing Time"
      timeline_info: "Please allow the following time for the refund to appear in your account:"
      credit_card_time: "Credit cards: 5-10 business days"
      debit_card_time: "Debit cards: 5-10 business days"
      bank_transfer_time: "Bank transfers: 3-7 business days"
      account_statement_note: "The refund will appear on your statement as a credit from our store."
      what_happens_next: "What Happens Next"
      automatic_process: "The refund will be automatically credited to your original payment method."
      no_action_required: "No further action is required from you."
      contact_info: "If you don't see the refund after the specified time, please contact your bank or our support team."
      thank_you: "Thank you for your patience and understanding."

    # payment_cancelled.html.twig
    cancelled:
      title: "Payment Cancelled"
      header: "Payment Cancelled"
      greeting: "Dear %customer_name%"
      notification: "Your payment has been cancelled."
      cancelled_date: "Cancellation Date"
      cancellation_reason: "Cancellation Reason"
      what_this_means: "What This Means"
      no_charge_info: "No funds have been charged to your account."
      authorization_voided: "Any pending authorization has been voided."
      next_steps: "Next Steps"
      order_not_processed: "Your order has not been processed"
      funds_released: "Any held funds will be released within 1-3 business days"
      can_place_new_order: "You can place a new order at any time"
      place_new_order: "Place New Order"
      apology: "We apologize if this caused any inconvenience."
      questions: "If you have any questions about this cancellation, please contact our support team."
```

## Usage Example

### In Event Subscriber (PHP)

```php
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class PaymentCapturedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
    ) {}

    public function onPaymentCaptured(PaymentCaptured $event): void
    {
        $payment = $event->payment;

        $htmlBody = $this->twig->render('emails/payment/payment_captured.html.twig', [
            'payment_id' => $payment->id()->toString(),
            'amount' => number_format($payment->amount()->getAmount() / 100, 2),
            'currency' => $payment->amount()->getCurrency(),
            'customer_name' => $payment->customerName(),
            'customer_email' => $payment->customerEmail(),
            'date' => $payment->capturedAt(),
            'order_id' => $payment->orderId()->toString(),
            'orderViewUrl' => 'https://example.com/orders/' . $payment->orderId()->toString(),
        ]);

        $email = (new Email())
            ->from('noreply@example.com')
            ->to($payment->customerEmail())
            ->subject('Payment Confirmation')
            ->html($htmlBody);

        $this->mailer->send($email);
    }
}
```

## Design Features

### Color Scheme

- **Success (Captured)**: Green gradient (#10b981 → #059669)
- **Error (Failed)**: Red gradient (#ef4444 → #dc2626)
- **Info (Refunded)**: Blue gradient (#3b82f6 → #2563eb)
- **Warning (Cancelled)**: Orange gradient (#f59e0b → #d97706)

### Responsive Design

- Mobile-friendly with max-width: 600px
- Scales down gracefully on smaller screens
- Maintains readability across devices

### Accessibility

- High contrast text for readability
- Clear visual hierarchy
- Semantic HTML structure
- Icon indicators for quick identification

## Best Practices

1. **Error Messages**: Always sanitize error messages before displaying (use `striptags` and `slice`)
2. **Dates**: Use consistent date format (`d/m/Y H:i` or locale-specific)
3. **Amounts**: Format amounts with 2 decimal places
4. **Links**: Always provide fallback if optional URLs are not defined
5. **Translation**: All user-facing text uses translation keys
6. **Security**: Never expose sensitive payment details (full card numbers, CVV, etc.)

## Testing

Test emails with different locales:

```bash
# Send test email (create test command)
symfony console app:send-test-email payment_captured john@example.com --locale=en
symfony console app:send-test-email payment_failed john@example.com --locale=fr
symfony console app:send-test-email payment_refunded john@example.com --locale=de
```

## Related Documentation

- Base email template: `templates/emails/base.html.twig`
- Order email templates: `templates/emails/order/`
- Translation files: `translations/emails.{locale}.yaml`
- Payment domain events: `src/Payment/Domain/Event/`
