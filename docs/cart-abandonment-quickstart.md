# Cart Abandonment System - Quick Start Guide

## Installation

### 1. Run the Migration

```bash
cd /var/www/new_ecom/backend
symfony console doctrine:migrations:migrate
```

This adds the `abandonment_email_sent` column and index to the `carts` table.

### 2. Verify Command Works

```bash
# Test in dry-run mode
symfony console app:cart:process-abandoned --dry-run

# Expected output:
# Cart Abandonment Recovery
# ========================
# Processing abandoned carts...
# DRY-RUN: Would process abandoned carts (no emails sent)
# Results
# Metric: Processed: 0
# Emails Sent: 0
# ...
```

### 3. Set Up Cron Job

Add to crontab (run every 6 hours):

```bash
crontab -e

# Add this line:
0 */6 * * * cd /var/www/new_ecom/backend && /usr/bin/php bin/console app:cart:process-abandoned >> /var/log/cart-abandonment.log 2>&1
```

Or use a more specific time:

```bash
# Run at 2am, 8am, 2pm, 8pm daily
0 2,8,14,20 * * * cd /var/www/new_ecom/backend && /usr/bin/php bin/console app:cart:process-abandoned
```

## Configuration (Optional)

### Environment Variables

Add to `.env.local`:

```env
# Cart abandonment settings
CART_ABANDONMENT_SENDER_EMAIL=no-reply@yourdomain.com
CART_ABANDONMENT_SENDER_NAME="Your Store Name"
CART_ABANDONMENT_STOREFRONT_URL=https://yourdomain.com
```

### Mailer Configuration

Ensure Symfony Mailer is configured in `config/packages/mailer.yaml`:

```yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
```

And in `.env.local`:

```env
MAILER_DSN=smtp://user:pass@smtp.example.com:587
```

## Testing the System

### 1. Create a Test Abandoned Cart

```sql
-- Create a test cart (replace values as needed)
INSERT INTO carts (id, tenant_id, customer_id, status, created_at, updated_at, abandonment_email_sent)
VALUES (
    '01JDTEST00000000000000001', -- Cart ID
    '00000000-0000-4000-8000-000000000001', -- Your tenant ID
    'customer-id-here', -- Your customer ID
    'active',
    NOW() - INTERVAL '25 hours',
    NOW() - INTERVAL '25 hours', -- 25 hours ago = abandoned
    FALSE
);

-- Add an item to the cart
INSERT INTO cart_items (id, cart_id, product_id, quantity, unit_price_amount, unit_price_currency)
VALUES (
    '01JDITEM00000000000000001',
    '01JDTEST00000000000000001',
    'product-id-here',
    2,
    1999, -- $19.99 in cents
    'USD'
);
```

### 2. Run the Command

```bash
symfony console app:cart:process-abandoned
```

### 3. Check Results

```bash
# Check if email was marked as sent
SELECT id, customer_id, updated_at, abandonment_email_sent
FROM carts
WHERE id = '01JDTEST00000000000000001';

# Should show: abandonment_email_sent = TRUE
```

### 4. Check Logs

```bash
tail -f var/log/dev.log | grep -i abandon
```

## Monitoring

### Check Processing Statistics

The command outputs statistics after each run:

```
Results
┌────────────┬───────┐
│ Metric     │ Count │
├────────────┼───────┤
│ Processed  │ 15    │
│ Emails Sent│ 12    │
│ Skipped    │ 2     │
│ Errors     │ 1     │
└────────────┴───────┘

Successfully processed 15 abandoned cart(s). Sent 12 email(s).
```

### Query Abandoned Carts

```sql
-- Find carts eligible for abandonment email
SELECT
    c.id,
    c.customer_id,
    c.updated_at,
    c.abandonment_email_sent,
    (SELECT COUNT(*) FROM cart_items WHERE cart_id = c.id) as item_count
FROM carts c
WHERE c.status = 'active'
AND c.updated_at < NOW() - INTERVAL '24 hours'
AND c.abandonment_email_sent = FALSE
AND c.customer_id IS NOT NULL
AND (SELECT COUNT(*) FROM cart_items WHERE cart_id = c.id) > 0
ORDER BY c.updated_at ASC
LIMIT 100;
```

### Reset Email Flag (Testing Only)

```sql
-- Reset flag to re-send email for testing
UPDATE carts
SET abandonment_email_sent = FALSE
WHERE id = 'cart-id-here';
```

## Troubleshooting

### Problem: No emails being sent

**Solution 1**: Check cart criteria

```sql
SELECT
    COUNT(*) as total_active_carts,
    COUNT(CASE WHEN customer_id IS NOT NULL THEN 1 END) as with_customer,
    COUNT(CASE WHEN updated_at < NOW() - INTERVAL '24 hours' THEN 1 END) as abandoned,
    COUNT(CASE WHEN abandonment_email_sent = TRUE THEN 1 END) as email_sent
FROM carts
WHERE status = 'active';
```

**Solution 2**: Check mailer configuration

```bash
symfony console debug:config framework mailer
```

**Solution 3**: Check customer emails exist

```sql
SELECT c.id, cu.email
FROM carts c
JOIN customers cu ON c.customer_id = cu.id
WHERE c.status = 'active'
AND c.customer_id IS NOT NULL;
```

### Problem: Command not found

**Solution**: Clear Symfony cache

```bash
symfony console cache:clear
symfony console list | grep abandon
```

### Problem: Migration fails

**Solution**: Check if column already exists

```sql
\d carts
-- Look for: abandonment_email_sent column
```

If it exists, mark migration as executed:

```bash
symfony console doctrine:migrations:version Version20251126120000 --add
```

## Email Preview

You can preview the email template by creating a test route:

```php
// src/Controller/TestController.php (DEV ONLY!)
#[Route('/dev/email/cart-abandoned', name: 'dev_email_cart_abandoned')]
public function previewCartAbandonedEmail(): Response
{
    return $this->render('emails/cart/cart_abandoned.html.twig', [
        'locale' => 'en',
        'customerName' => 'John Doe',
        'itemCount' => 3,
        'total' => '157.47',
        'currency' => 'USD',
        'expiresAt' => new \DateTimeImmutable('+7 days'),
        'cartUrl' => 'http://localhost:3000/cart',
    ]);
}
```

Then visit: `http://localhost:8000/dev/email/cart-abandoned`

## Performance Tips

1. **Run during off-peak hours**: Schedule for low-traffic times (e.g., 2am, 8am)
2. **Batch size**: Default is 100 carts per run, adjust in `CartAbandonmentService::BATCH_SIZE` if needed
3. **Monitor execution time**: Add to cron log and monitor duration
4. **Index maintenance**: Ensure `idx_carts_abandonment_email` index exists

## Security Notes

1. **Email privacy**: Only authenticated customers with verified emails receive notifications
2. **Rate limiting**: Batch processing prevents email spam
3. **Error isolation**: Email failures don't expose system details to users
4. **Logging**: All operations logged for audit trail

## Support

For issues or questions:
- Check logs: `var/log/dev.log` or `var/log/prod.log`
- Review documentation: `/var/www/new_ecom/backend/docs/cart-abandonment-system.md`
- Run with verbose flag: `symfony console app:cart:process-abandoned -vvv`

---

**Quick Reference**: All commands assume you're in `/var/www/new_ecom/backend` directory
