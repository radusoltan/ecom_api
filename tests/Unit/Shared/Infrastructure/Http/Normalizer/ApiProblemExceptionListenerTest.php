<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http\Normalizer;

use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Exception\InsufficientStockException;
use App\Cart\Domain\Exception\InvalidQuantityException;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Order\Domain\Exception\CheckoutValidationException;
use App\Payment\Domain\Exception\PaymentGatewayException;
use App\Shared\Infrastructure\Http\Normalizer\ApiProblemExceptionListener;
use App\User\Domain\Exception\EmailAlreadyExistsException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiProblemExceptionListenerTest extends TestCase
{
    private ApiProblemExceptionListener $listener;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->listener = new ApiProblemExceptionListener(
            new NullLogger(),
            'test',
        );
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    #[Test]
    public function itSubscribesToKernelExceptionEvent(): void
    {
        $events = ApiProblemExceptionListener::getSubscribedEvents();
        $this->assertArrayHasKey(KernelEvents::EXCEPTION, $events);
    }

    #[Test]
    public function itIgnoresNonApiRequests(): void
    {
        $request = Request::create('/admin/dashboard');
        $event = new ExceptionEvent(
            $this->kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new \RuntimeException('Error'),
        );

        $this->listener->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    #[Test]
    public function itReturnsProblemJsonContentType(): void
    {
        $event = $this->createApiExceptionEvent(
            new \RuntimeException('Something broke'),
            '/api/v1/products',
        );

        $this->listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itNormalizesProductNotFoundTo404(): void
    {
        $exception = new ProductNotFoundException('Product with ID "abc-123" not found');
        $event = $this->createApiExceptionEvent($exception, '/api/v1/products/abc-123');

        $this->listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('https://api.ecommerce.example/problems/resource-not-found', $body['type']);
        $this->assertSame('Resource Not Found', $body['title']);
        $this->assertSame(404, $body['status']);
        $this->assertSame('/api/v1/products/abc-123', $body['instance']);
        $this->assertNotEmpty($body['detail']);
    }

    #[Test]
    public function itNormalizesCartNotFoundTo404(): void
    {
        $exception = CartNotFoundException::withId('cart-xyz');
        $event = $this->createApiExceptionEvent($exception, '/api/v1/carts/cart-xyz');

        $this->listener->onKernelException($event);

        $body = $this->getResponseBody($event);
        $this->assertSame(404, $body['status']);
        $this->assertSame('https://api.ecommerce.example/problems/resource-not-found', $body['type']);
    }

    #[Test]
    public function itNormalizesInsufficientStockTo409(): void
    {
        $exception = new InsufficientStockException('Not enough stock');
        $event = $this->createApiExceptionEvent($exception, '/api/v1/cart/items');

        $this->listener->onKernelException($event);

        $body = $this->getResponseBody($event);
        $this->assertSame(409, $body['status']);
        $this->assertSame('https://api.ecommerce.example/problems/stock-insufficient', $body['type']);
        $this->assertSame('Insufficient Stock', $body['title']);
    }

    #[Test]
    public function itNormalizesEmailAlreadyExistsTo409(): void
    {
        $exception = new EmailAlreadyExistsException('Email already taken');
        $event = $this->createApiExceptionEvent($exception, '/api/v1/users');

        $this->listener->onKernelException($event);

        $body = $this->getResponseBody($event);
        $this->assertSame(409, $body['status']);
        $this->assertSame('https://api.ecommerce.example/problems/resource-conflict', $body['type']);
    }

    #[Test]
    public function itNormalizesInvalidQuantityTo400(): void
    {
        $exception = new InvalidQuantityException('Quantity must be positive');
        $event = $this->createApiExceptionEvent($exception, '/api/v1/cart/items');

        $this->listener->onKernelException($event);

        $body = $this->getResponseBody($event);
        $this->assertSame(400, $body['status']);
        $this->assertSame('https://api.ecommerce.example/problems/validation-failed', $body['type']);
    }

    #[Test]
    public function itNormalizesCheckoutValidationTo422(): void
    {
        $exception = new CheckoutValidationException('Missing shipping address');
        $event = $this->createApiExceptionEvent($exception, '/api/v1/orders');

        $this->listener->onKernelException($event);

        $body = $this->getResponseBody($event);
        $this->assertSame(422, $body['status']);
        $this->assertSame('https://api.ecommerce.example/problems/checkout-validation-failed', $body['type']);
    }

    #[Test]
    public function itNormalizesPaymentGatewayExceptionTo402WithEnrichedData(): void
    {
        $exception = new PaymentGatewayException(
            'Card declined',
            'card_declined',
            'Your card was declined by the issuer',
            'do_not_honor',
        );
        $event = $this->createApiExceptionEvent($exception, '/api/v1/payments');

        $this->listener->onKernelException($event);

        $body = $this->getResponseBody($event);
        $this->assertSame(402, $body['status']);
        $this->assertSame('https://api.ecommerce.example/problems/payment-failed', $body['type']);
        $this->assertSame('card_declined', $body['gateway_error_code']);
        $this->assertFalse($body['retryable']);
        $this->assertStringContainsString('declined', $body['detail']);
    }

    #[Test]
    public function itNormalizesSymfony401ToProblemJson(): void
    {
        $exception = new UnauthorizedHttpException('Bearer', 'JWT Token not found');
        $event = $this->createApiExceptionEvent($exception, '/api/v1/products');

        $this->listener->onKernelException($event);

        $body = $this->getResponseBody($event);
        $this->assertSame(401, $body['status']);
        $this->assertSame('https://api.ecommerce.example/problems/unauthorized', $body['type']);
        $this->assertSame('Unauthorized', $body['title']);
    }

    #[Test]
    public function itNormalizesSymfony403ToProblemJson(): void
    {
        $exception = new AccessDeniedHttpException('Access denied');
        $event = $this->createApiExceptionEvent($exception, '/api/v1/admin/users');

        $this->listener->onKernelException($event);

        $body = $this->getResponseBody($event);
        $this->assertSame(403, $body['status']);
        $this->assertSame('https://api.ecommerce.example/problems/forbidden', $body['type']);
    }

    #[Test]
    public function itNormalizesSymfony404ToProblemJson(): void
    {
        $exception = new NotFoundHttpException('Route not found');
        $event = $this->createApiExceptionEvent($exception, '/api/v1/nonexistent');

        $this->listener->onKernelException($event);

        $body = $this->getResponseBody($event);
        $this->assertSame(404, $body['status']);
        $this->assertSame('https://api.ecommerce.example/problems/resource-not-found', $body['type']);
    }

    #[Test]
    public function itNormalizesSymfony405ToProblemJson(): void
    {
        $exception = new MethodNotAllowedHttpException(['GET', 'POST'], 'PUT not allowed');
        $event = $this->createApiExceptionEvent($exception, '/api/v1/products');

        $this->listener->onKernelException($event);

        $body = $this->getResponseBody($event);
        $this->assertSame(405, $body['status']);
        $this->assertSame('https://api.ecommerce.example/problems/method-not-allowed', $body['type']);
    }

    #[Test]
    public function itNormalizesUnknownExceptionTo500(): void
    {
        $exception = new \Exception('Something completely unexpected');
        $event = $this->createApiExceptionEvent($exception, '/api/v1/products');

        $this->listener->onKernelException($event);

        $body = $this->getResponseBody($event);
        $this->assertSame(500, $body['status']);
        $this->assertSame('https://api.ecommerce.example/problems/internal-error', $body['type']);
        $this->assertSame('Internal Server Error', $body['title']);
    }

    #[Test]
    public function itHidesExceptionDetailsInProduction(): void
    {
        $prodListener = new ApiProblemExceptionListener(new NullLogger(), 'prod');
        $exception = new \Exception('Database connection failed: password=secret123');
        $event = $this->createApiExceptionEvent($exception, '/api/v1/products');

        $prodListener->onKernelException($event);

        $body = $this->getResponseBody($event);
        $this->assertSame(500, $body['status']);
        $this->assertStringNotContainsString('secret123', $body['detail']);
        $this->assertSame('An unexpected error occurred. Please try again later.', $body['detail']);
    }

    #[Test]
    public function itShowsExceptionDetailsInDev(): void
    {
        $devListener = new ApiProblemExceptionListener(new NullLogger(), 'dev');
        $exception = new \Exception('Database connection failed');
        $event = $this->createApiExceptionEvent($exception, '/api/v1/products');

        $devListener->onKernelException($event);

        $body = $this->getResponseBody($event);
        $this->assertSame('Database connection failed', $body['detail']);
    }

    #[Test]
    public function itIncludesInstanceInAllResponses(): void
    {
        $exception = new \RuntimeException('Generic error');
        $path = '/api/v1/products/123/variants';
        $event = $this->createApiExceptionEvent($exception, $path);

        $this->listener->onKernelException($event);

        $body = $this->getResponseBody($event);
        $this->assertSame($path, $body['instance']);
    }

    #[Test]
    public function itNormalizesDomainExceptionTo400(): void
    {
        $exception = new \DomainException('Invalid product state');
        $event = $this->createApiExceptionEvent($exception, '/api/v1/products');

        $this->listener->onKernelException($event);

        $body = $this->getResponseBody($event);
        $this->assertSame(400, $body['status']);
        $this->assertSame('https://api.ecommerce.example/problems/domain-error', $body['type']);
    }

    #[Test]
    public function allResponsesHaveRequiredRfc7807Fields(): void
    {
        $exceptions = [
            new ProductNotFoundException('Product not found'),
            new InsufficientStockException('No stock'),
            new InvalidQuantityException('Bad qty'),
            new AccessDeniedHttpException('Denied'),
            new \Exception('Unexpected'),
            new \DomainException('Domain issue'),
        ];

        foreach ($exceptions as $exception) {
            $event = $this->createApiExceptionEvent($exception, '/api/v1/test');
            $this->listener->onKernelException($event);
            $body = $this->getResponseBody($event);

            $this->assertArrayHasKey('type', $body, sprintf('Missing "type" for %s', $exception::class));
            $this->assertArrayHasKey('title', $body, sprintf('Missing "title" for %s', $exception::class));
            $this->assertArrayHasKey('status', $body, sprintf('Missing "status" for %s', $exception::class));
            $this->assertArrayHasKey('detail', $body, sprintf('Missing "detail" for %s', $exception::class));
            $this->assertArrayHasKey('instance', $body, sprintf('Missing "instance" for %s', $exception::class));
        }
    }

    private function createApiExceptionEvent(\Throwable $exception, string $path): ExceptionEvent
    {
        $request = Request::create($path);

        return new ExceptionEvent(
            $this->kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getResponseBody(ExceptionEvent $event): array
    {
        $response = $event->getResponse();
        $this->assertNotNull($response);

        return json_decode((string) $response->getContent(), true);
    }
}
