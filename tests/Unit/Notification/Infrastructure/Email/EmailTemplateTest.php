<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Infrastructure\Email;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\Translator;
use Twig\Environment;

/**
 * Email Template Rendering Tests.
 *
 * Tests all email templates for:
 * - Correct rendering with expected variables
 * - Translation key existence
 * - Template structure (extends base)
 * - Variable substitution
 * - HTML output quality
 */
final class EmailTemplateTest extends KernelTestCase
{
    private Environment $twig;
    private Translator $translator;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->twig = $container->get('twig');
        $this->translator = $container->get('translator');
    }

    public function testBaseEmailTemplateRendersWithoutErrors(): void
    {
        $html = $this->twig->render('emails/base.html.twig', [
            'locale' => 'en',
        ]);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringContainsString('email-container', $html);
        $this->assertStringContainsString('email-header', $html);
        $this->assertStringContainsString('email-content', $html);
        $this->assertStringContainsString('email-footer', $html);
    }

    public function testOrderPlacedEmailRendersCorrectly(): void
    {
        $html = $this->twig->render('emails/order/order_placed.html.twig', [
            'customerName' => 'John Doe',
            'orderId' => 'ORD-123456',
            'orderDate' => new \DateTimeImmutable('2025-11-07 14:30:00'),
            'total' => 99.99,
            'currency' => 'USD',
            'status' => 'confirmed',
            'orderViewUrl' => 'https://example.com/order/ORD-123456',
            'items' => [
                ['name' => 'Product A', 'quantity' => 2, 'price' => 29.99],
                ['name' => 'Product B', 'quantity' => 1, 'price' => 39.99],
            ],
            'shippingAddress' => [
                'street' => '123 Main St',
                'city' => 'New York',
                'postalCode' => '10001',
                'country' => 'USA',
            ],
        ]);

        // Test dynamic content is rendered (not translated keys)
        $this->assertStringContainsString('ORD-123456', $html);
        $this->assertStringContainsString('99.99 USD', $html);
        $this->assertStringContainsString('Product A', $html);
        $this->assertStringContainsString('Product B', $html);
        $this->assertStringContainsString('123 Main St', $html);
        $this->assertStringContainsString('New York', $html);
        $this->assertStringContainsString('10001', $html);

        // Test structure exists
        $this->assertStringContainsString('email-container', $html);
        $this->assertStringContainsString('info-box', $html);
    }

    public function testOrderPaidEmailRendersCorrectly(): void
    {
        $html = $this->twig->render('emails/order/order_paid.html.twig', [
            'customerName' => 'Jane Smith',
            'orderId' => 'ORD-789012',
            'paymentDate' => new \DateTimeImmutable('2025-11-07 15:00:00'),
            'total' => 149.99,
            'currency' => 'EUR',
            'paymentMethod' => 'Credit Card',
            'transactionId' => 'TXN-ABC123',
            'orderViewUrl' => 'https://example.com/order/ORD-789012',
        ]);

        // Test dynamic content is rendered
        $this->assertStringContainsString('ORD-789012', $html);
        $this->assertStringContainsString('149.99 EUR', $html);
        $this->assertStringContainsString('Credit Card', $html);
        $this->assertStringContainsString('TXN-ABC123', $html);
        $this->assertStringContainsString('info-box', $html);
    }

    public function testOrderShippedEmailRendersCorrectly(): void
    {
        $html = $this->twig->render('emails/order/order_shipped.html.twig', [
            'customerName' => 'Bob Johnson',
            'orderId' => 'ORD-345678',
            'shippedDate' => new \DateTimeImmutable('2025-11-07 16:00:00'),
            'carrier' => 'DHL',
            'trackingNumber' => 'DHL123456789',
            'estimatedDelivery' => new \DateTimeImmutable('2025-11-10'),
            'trackingUrl' => 'https://tracking.dhl.com/DHL123456789',
            'orderViewUrl' => 'https://example.com/order/ORD-345678',
            'shippingAddress' => [
                'street' => '456 Oak Ave',
                'city' => 'Los Angeles',
                'postalCode' => '90001',
                'country' => 'USA',
            ],
        ]);

        $this->assertStringContainsString('ORD-345678', $html);
        $this->assertStringContainsString('DHL', $html);
        $this->assertStringContainsString('DHL123456789', $html);
        $this->assertStringContainsString('456 Oak Ave', $html);
        $this->assertStringContainsString('Los Angeles', $html);
    }

    public function testOrderDeliveredEmailRendersCorrectly(): void
    {
        $html = $this->twig->render('emails/order/order_delivered.html.twig', [
            'customerName' => 'Alice Williams',
            'orderId' => 'ORD-901234',
            'deliveryDate' => '2025-11-07',
            'deliveryTime' => '14:30',
            'deliveryMethod' => 'Standard Delivery',
            'orderViewUrl' => 'https://example.com/order/ORD-901234',
        ]);

        $this->assertStringContainsString('ORD-901234', $html);
        $this->assertStringContainsString('2025-11-07', $html);
        $this->assertStringContainsString('14:30', $html);
        $this->assertStringContainsString('Standard Delivery', $html);
    }

    public function testOrderCancelledEmailRendersCorrectly(): void
    {
        $html = $this->twig->render('emails/order/order_cancelled.html.twig', [
            'customerName' => 'Charlie Brown',
            'orderId' => 'ORD-567890',
            'cancelledDate' => new \DateTimeImmutable('2025-11-07 17:00:00'),
            'total' => 79.99,
            'currency' => 'GBP',
            'cancellationReason' => 'Customer request',
            'refundAmount' => 79.99,
            'refundMethod' => 'Original payment method',
            'orderViewUrl' => 'https://example.com/order/ORD-567890',
        ]);

        $this->assertStringContainsString('ORD-567890', $html);
        $this->assertStringContainsString('79.99 GBP', $html);
        $this->assertStringContainsString('Customer request', $html);
        $this->assertStringContainsString('Original payment method', $html);
    }

    public function testEmailTemplatesContainTranslationKeys(): void
    {
        // Order Placed
        $translation = $this->translator->trans('emails.order.placed.title', [], 'emails', 'en');
        $this->assertSame('Order Confirmation', $translation);

        // Order Paid
        $translation = $this->translator->trans('emails.order.paid.title', [], 'emails', 'en');
        $this->assertSame('Payment Confirmed', $translation);

        // Order Shipped
        $translation = $this->translator->trans('emails.order.shipped.title', [], 'emails', 'en');
        $this->assertSame('Order Shipped', $translation);

        // Order Delivered
        $translation = $this->translator->trans('emails.order.delivered.title', [], 'emails', 'en');
        $this->assertSame('Delivery Confirmation', $translation);

        // Order Cancelled
        $translation = $this->translator->trans('emails.order.cancelled.title', [], 'emails', 'en');
        $this->assertSame('Order Cancelled', $translation);
    }

    public function testEmailTemplatesExtendBaseTemplate(): void
    {
        $templates = [
            'emails/order/order_placed.html.twig',
            'emails/order/order_paid.html.twig',
            'emails/order/order_shipped.html.twig',
            'emails/order/order_delivered.html.twig',
            'emails/order/order_cancelled.html.twig',
        ];

        foreach ($templates as $template) {
            $source = $this->twig->getLoader()->getSourceContext($template)->getCode();
            $this->assertStringContainsString("{% extends 'emails/base.html.twig' %}", $source);
        }
    }

    public function testEmailFooterContainsRequiredElements(): void
    {
        $html = $this->twig->render('emails/base.html.twig', [
            'locale' => 'en',
        ]);

        // Test footer structure exists
        $this->assertStringContainsString('email-footer', $html);
        $this->assertStringContainsString('divider', $html);

        // Test footer has proper styling
        $this->assertStringContainsString('.email-footer', $html);
        $this->assertStringContainsString('background-color: #f8f9fa', $html);
        $this->assertStringContainsString('border-top: 1px solid #e0e0e0', $html);
    }

    public function testEmailTemplatesHandleMissingOptionalVariables(): void
    {
        // Test order placed without items and shipping address
        $html = $this->twig->render('emails/order/order_placed.html.twig', [
            'customerName' => 'Test User',
            'orderId' => 'ORD-TEST',
            'orderDate' => new \DateTimeImmutable(),
            'total' => 50.00,
            'currency' => 'USD',
            'status' => 'pending',
            'orderViewUrl' => 'https://example.com/order/ORD-TEST',
        ]);

        $this->assertStringContainsString('ORD-TEST', $html);
        $this->assertStringContainsString('50', $html); // Amount appears in some form
        $this->assertStringContainsString('email-container', $html);

        // Test order cancelled without refund
        $html = $this->twig->render('emails/order/order_cancelled.html.twig', [
            'customerName' => 'Test User',
            'orderId' => 'ORD-TEST2',
            'cancelledDate' => new \DateTimeImmutable(),
            'total' => 50.00,
            'currency' => 'USD',
            'orderViewUrl' => 'https://example.com/order/ORD-TEST2',
        ]);

        $this->assertStringContainsString('ORD-TEST2', $html);
        $this->assertStringContainsString('email-container', $html);
    }
}
