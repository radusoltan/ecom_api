<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notifications\Infrastructure\Email;

use App\Notifications\Infrastructure\Email\SymfonyEmailSender;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Integration test for SymfonyEmailSender service.
 *
 * Tests email template rendering and sending functionality.
 *
 * @covers \App\Notifications\Infrastructure\Email\SymfonyEmailSender
 */
final class SymfonyEmailSenderTest extends KernelTestCase
{
    private SymfonyEmailSender $emailSender;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = self::getContainer();

        // Get the SymfonyEmailSender service from the container
        $this->emailSender = $container->get(SymfonyEmailSender::class);
    }

    /**
     * Test that the service can be instantiated from the container.
     */
    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(SymfonyEmailSender::class, $this->emailSender);
    }

    /**
     * Test sending email with order_placed template.
     *
     * This test verifies that:
     * - Templates can be loaded correctly
     * - Context variables are passed properly
     * - Both HTML and text templates are rendered
     * - Email is queued for sending (with null transport, no actual sending occurs)
     */
    public function testSendOrderPlacedEmail(): void
    {
        // Arrange
        $to = 'customer@example.com';
        $subject = 'Order Confirmation';
        $templateName = 'order/order_placed';
        $context = [
            'customerName' => 'John Doe',
            'orderId' => 'ORD-12345',
            'orderDate' => new \DateTimeImmutable('2025-01-15 10:30:00'),
            'total' => '99.99',
            'currency' => 'EUR',
            'status' => 'pending',
            'items' => [
                ['name' => 'Product A', 'quantity' => 2, 'price' => '29.99'],
                ['name' => 'Product B', 'quantity' => 1, 'price' => '39.99'],
            ],
            'shippingAddress' => [
                'street' => '123 Main St',
                'city' => 'Paris',
                'postalCode' => '75001',
                'country' => 'France',
            ],
            'orderViewUrl' => 'https://example.com/orders/ORD-12345',
        ];

        // Act & Assert - should not throw exception with null transport
        try {
            $this->emailSender->send($to, $subject, $templateName, $context);
            $this->assertTrue(true, 'Email sent successfully without exceptions');
        } catch (TransportExceptionInterface $e) {
            $this->fail('Email sending failed: ' . $e->getMessage());
        }
    }

    /**
     * Test sending email with payment_captured template.
     */
    public function testSendPaymentCapturedEmail(): void
    {
        // Arrange
        $to = 'customer@example.com';
        $subject = 'Payment Confirmation';
        $templateName = 'payment/payment_captured';
        $context = [
            'customerName' => 'Jane Smith',
            'paymentId' => 'PAY-67890',
            'orderId' => 'ORD-54321',
            'amount' => '149.99',
            'currency' => 'EUR',
            'paymentDate' => new \DateTimeImmutable('2025-01-15 11:00:00'),
            'orderViewUrl' => 'https://example.com/orders/ORD-54321',
        ];

        // Act & Assert
        try {
            $this->emailSender->send($to, $subject, $templateName, $context);
            $this->assertTrue(true, 'Payment email sent successfully');
        } catch (TransportExceptionInterface $e) {
            $this->fail('Payment email sending failed: ' . $e->getMessage());
        }
    }

    /**
     * Test sending email with custom locale.
     */
    public function testSendEmailWithCustomLocale(): void
    {
        // Arrange
        $to = 'customer@example.fr';
        $subject = 'Confirmation de commande';
        $templateName = 'order/order_placed';
        $context = [
            'customerName' => 'Pierre Dupont',
            'orderId' => 'ORD-99999',
            'orderDate' => new \DateTimeImmutable(),
            'total' => '79.99',
            'currency' => 'EUR',
            'status' => 'pending',
            'orderViewUrl' => 'https://example.com/orders/ORD-99999',
        ];
        $locale = 'fr';

        // Act & Assert
        try {
            $this->emailSender->send($to, $subject, $templateName, $context, $locale);
            $this->assertTrue(true, 'Email with French locale sent successfully');
        } catch (TransportExceptionInterface $e) {
            $this->fail('Email with locale failed: ' . $e->getMessage());
        }
    }

    /**
     * Test sending email with custom sender address.
     */
    public function testSendEmailWithCustomFromAddress(): void
    {
        // Arrange
        $to = 'customer@example.com';
        $subject = 'Order Update';
        $templateName = 'order/order_placed';
        $context = [
            'customerName' => 'Test Customer',
            'orderId' => 'ORD-11111',
            'orderDate' => new \DateTimeImmutable(),
            'total' => '50.00',
            'currency' => 'EUR',
            'status' => 'confirmed',
            'orderViewUrl' => 'https://example.com/orders/ORD-11111',
        ];
        $customFrom = 'support@example.com';

        // Act & Assert
        try {
            $this->emailSender->send($to, $subject, $templateName, $context, null, $customFrom);
            $this->assertTrue(true, 'Email with custom sender sent successfully');
        } catch (TransportExceptionInterface $e) {
            $this->fail('Email with custom sender failed: ' . $e->getMessage());
        }
    }

    /**
     * Test sending batch emails.
     */
    public function testSendBatchEmails(): void
    {
        // Arrange
        $recipients = [
            'customer1@example.com',
            'customer2@example.com',
            'customer3@example.com',
        ];
        $subject = 'Newsletter';
        $templateName = 'order/order_placed'; // Reusing template for test
        $context = [
            'customerName' => 'Valued Customer',
            'orderId' => 'NEWSLETTER-001',
            'orderDate' => new \DateTimeImmutable(),
            'total' => '0.00',
            'currency' => 'EUR',
            'status' => 'pending',
            'orderViewUrl' => 'https://example.com/newsletter',
        ];

        // Act
        $result = $this->emailSender->sendBatch($recipients, $subject, $templateName, $context);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals(3, $result['sent']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * Test password reset email template.
     */
    public function testSendPasswordResetEmail(): void
    {
        // Arrange
        $to = 'user@example.com';
        $subject = 'Password Reset Request';
        $templateName = 'password_reset';
        $context = [
            'userEmail' => 'user@example.com',
            'resetUrl' => 'https://example.com/reset?token=abc123',
            'token' => 'abc123',
            'expiresInHours' => 24,
        ];

        // Act & Assert
        try {
            $this->emailSender->send($to, $subject, $templateName, $context);
            $this->assertTrue(true, 'Password reset email sent successfully');
        } catch (TransportExceptionInterface $e) {
            $this->fail('Password reset email failed: ' . $e->getMessage());
        }
    }

    /**
     * Test sending email with template that only has HTML version.
     *
     * This should succeed even without a text template.
     */
    public function testSendEmailWithOnlyHtmlTemplate(): void
    {
        // Arrange
        $to = 'customer@example.com';
        $subject = 'Test Email';
        $templateName = 'base'; // Base template without dedicated txt version
        $context = [
            'locale' => 'en',
        ];

        // Act & Assert
        try {
            $this->emailSender->send($to, $subject, $templateName, $context);
            $this->assertTrue(true, 'Email with only HTML template sent successfully');
        } catch (TransportExceptionInterface $e) {
            $this->fail('Email with only HTML template failed: ' . $e->getMessage());
        }
    }

    /**
     * Test cart abandoned email template.
     */
    public function testSendCartAbandonedEmail(): void
    {
        // Arrange
        $to = 'customer@example.com';
        $subject = 'You left items in your cart';
        $templateName = 'cart/cart_abandoned';
        $context = [
            'customerName' => 'John Doe',
            'itemCount' => 3,
            'total' => '129.97',
            'currency' => 'EUR',
            'expiresAt' => new \DateTimeImmutable('+7 days'),
            'items' => [
                ['name' => 'Product A', 'quantity' => 1, 'price' => '49.99'],
                ['name' => 'Product B', 'quantity' => 2, 'price' => '39.99'],
            ],
            'cartUrl' => 'https://example.com/cart',
            'discount' => '10%',
        ];

        // Act & Assert
        try {
            $this->emailSender->send($to, $subject, $templateName, $context);
            $this->assertTrue(true, 'Cart abandoned email sent successfully');
        } catch (TransportExceptionInterface $e) {
            $this->fail('Cart abandoned email failed: ' . $e->getMessage());
        }
    }
}
