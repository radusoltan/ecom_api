<?php

declare(strict_types=1);

namespace App\Inventory\Application\EventSubscriber;

use App\Inventory\Domain\Event\StockDepleted;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Handles StockDepleted domain events by sending low stock alerts.
 *
 * Business Rules:
 * - Send alert email immediately when stock becomes depleted (available <= threshold)
 * - Email includes stock item ID, product ID, warehouse ID, and current quantities
 * - Alert goes to inventory managers and purchasing team
 * - Failures should be logged but not block stock operations
 */
final readonly class StockDepletedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $alertEmail = 'inventory@ecommerce.local',
        private string $senderEmail = 'alerts@ecommerce.local',
        private string $senderName = 'Inventory Alert System'
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StockDepleted::class => 'onStockDepleted',
        ];
    }

    public function onStockDepleted(StockDepleted $event): void
    {
        try {
            $this->sendLowStockAlert($event);

            $this->logger->warning('Low stock alert sent', [
                'stockItemId' => $event->stockItemId->toString(),
                'productId' => $event->productId->toString(),
                'warehouseId' => $event->warehouseId->toString(),
                'availableQuantity' => $event->availableQuantity->value(),
                'threshold' => $event->threshold->value(),
                'tenantId' => $event->tenantId->toString(),
            ]);
        } catch (\Throwable $exception) {
            // Log error but don't throw - email failure shouldn't block stock operations
            $this->logger->error('Failed to send low stock alert', [
                'stockItemId' => $event->stockItemId->toString(),
                'productId' => $event->productId->toString(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    private function sendLowStockAlert(StockDepleted $event): void
    {
        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
            ->to($this->alertEmail)
            ->subject(sprintf('⚠️ Low Stock Alert - Product %s', $event->productId->toString()))
            ->html($this->buildEmailBody($event))
            ->text($this->buildEmailTextBody($event))
            ->priority(Email::PRIORITY_HIGH);

        $this->mailer->send($email);
    }

    private function buildEmailBody(StockDepleted $event): string
    {
        $stockItemId = $event->stockItemId->toString();
        $productId = $event->productId->toString();
        $warehouseId = $event->warehouseId->toString();
        $available = $event->availableQuantity->value();
        $threshold = $event->threshold->value();
        $tenantId = $event->tenantId->toString();

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #FF9800; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; margin: 20px 0; }
                .alert-box { background: #FFF3CD; border-left: 4px solid #FF9800; padding: 15px; margin: 15px 0; }
                .stock-details { background: white; padding: 15px; margin: 15px 0; border: 1px solid #ddd; }
                .stock-details table { width: 100%; border-collapse: collapse; }
                .stock-details td { padding: 8px; border-bottom: 1px solid #eee; }
                .stock-details td:first-child { font-weight: bold; width: 40%; }
                .critical { color: #D32F2F; font-weight: bold; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>⚠️ Low Stock Alert</h1>
                </div>
                <div class="content">
                    <div class="alert-box">
                        <p><strong>Action Required:</strong> Stock levels have fallen below the configured threshold.</p>
                        <p class="critical">Available Quantity: {$available} units (Threshold: {$threshold} units)</p>
                    </div>

                    <div class="stock-details">
                        <h3>Stock Item Details</h3>
                        <table>
                            <tr>
                                <td>Stock Item ID:</td>
                                <td>{$stockItemId}</td>
                            </tr>
                            <tr>
                                <td>Product ID:</td>
                                <td>{$productId}</td>
                            </tr>
                            <tr>
                                <td>Warehouse ID:</td>
                                <td>{$warehouseId}</td>
                            </tr>
                            <tr>
                                <td>Tenant ID:</td>
                                <td>{$tenantId}</td>
                            </tr>
                            <tr>
                                <td>Available Quantity:</td>
                                <td class="critical">{$available} units</td>
                            </tr>
                            <tr>
                                <td>Low Stock Threshold:</td>
                                <td>{$threshold} units</td>
                            </tr>
                        </table>
                    </div>

                    <p><strong>Recommended Actions:</strong></p>
                    <ul>
                        <li>Review current demand and sales velocity</li>
                        <li>Create purchase order to replenish stock</li>
                        <li>Check for pending deliveries</li>
                        <li>Consider adjusting low stock threshold if needed</li>
                    </ul>

                    <p>Please take immediate action to prevent stockouts.</p>
                </div>
                <div class="footer">
                    <p>This is an automated alert from the Inventory Management System.</p>
                    <p>&copy; 2025 E-Commerce Platform. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    private function buildEmailTextBody(StockDepleted $event): string
    {
        $stockItemId = $event->stockItemId->toString();
        $productId = $event->productId->toString();
        $warehouseId = $event->warehouseId->toString();
        $available = $event->availableQuantity->value();
        $threshold = $event->threshold->value();
        $tenantId = $event->tenantId->toString();

        return <<<TEXT
        ⚠️ LOW STOCK ALERT

        Action Required: Stock levels have fallen below the configured threshold.

        Available Quantity: {$available} units (Threshold: {$threshold} units)

        Stock Item Details:
        - Stock Item ID: {$stockItemId}
        - Product ID: {$productId}
        - Warehouse ID: {$warehouseId}
        - Tenant ID: {$tenantId}
        - Available Quantity: {$available} units
        - Low Stock Threshold: {$threshold} units

        Recommended Actions:
        - Review current demand and sales velocity
        - Create purchase order to replenish stock
        - Check for pending deliveries
        - Consider adjusting low stock threshold if needed

        Please take immediate action to prevent stockouts.

        ---
        This is an automated alert from the Inventory Management System.
        © 2025 E-Commerce Platform. All rights reserved.
        TEXT;
    }
}
