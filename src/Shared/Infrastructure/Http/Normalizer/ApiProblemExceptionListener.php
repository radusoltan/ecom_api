<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Normalizer;

use App\Cart\Domain\Exception\CartItemNotFoundException;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Exception\InsufficientStockException;
use App\Cart\Domain\Exception\InvalidQuantityException;
use App\Catalog\Domain\Exception\CategoryNotFoundException;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Order\Domain\Exception\CheckoutValidationException;
use App\Payment\Domain\Exception\CardDeclinedException;
use App\Payment\Domain\Exception\InsufficientFundsException;
use App\Payment\Domain\Exception\InvalidPaymentMethodException;
use App\Payment\Domain\Exception\PaymentGatewayException;
use App\Payment\Domain\Exception\UnsupportedPaymentMethodException;
use App\Shared\Domain\Exception\InvalidLanguageCodeException;
use App\Shipping\Domain\Exception\InvalidShipmentTransitionException;
use App\Tenant\Domain\Exception\TenantAlreadyExistsException;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\User\Domain\Exception\EmailAlreadyExistsException;
use App\User\Domain\Exception\UsernameAlreadyExistsException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Normalizes all API exceptions to RFC 7807 Problem Details format.
 *
 * @see https://tools.ietf.org/html/rfc7807
 */
final readonly class ApiProblemExceptionListener implements EventSubscriberInterface
{
    private const PROBLEM_BASE_URI = 'https://api.ecommerce.example/problems';

    /** @var array<class-string<\Throwable>, array{status: int, type: string, title: string}> */
    private const DOMAIN_EXCEPTION_MAP = [
        // Not Found (404)
        ProductNotFoundException::class => ['status' => 404, 'type' => 'resource-not-found', 'title' => 'Resource Not Found'],
        CategoryNotFoundException::class => ['status' => 404, 'type' => 'resource-not-found', 'title' => 'Resource Not Found'],
        CartNotFoundException::class => ['status' => 404, 'type' => 'resource-not-found', 'title' => 'Resource Not Found'],
        CartItemNotFoundException::class => ['status' => 404, 'type' => 'resource-not-found', 'title' => 'Resource Not Found'],
        TenantNotFoundException::class => ['status' => 404, 'type' => 'resource-not-found', 'title' => 'Resource Not Found'],

        // Conflict (409)
        TenantAlreadyExistsException::class => ['status' => 409, 'type' => 'resource-conflict', 'title' => 'Resource Conflict'],
        EmailAlreadyExistsException::class => ['status' => 409, 'type' => 'resource-conflict', 'title' => 'Resource Conflict'],
        UsernameAlreadyExistsException::class => ['status' => 409, 'type' => 'resource-conflict', 'title' => 'Resource Conflict'],
        InsufficientStockException::class => ['status' => 409, 'type' => 'stock-insufficient', 'title' => 'Insufficient Stock'],
        InvalidShipmentTransitionException::class => ['status' => 409, 'type' => 'invalid-state-transition', 'title' => 'Invalid State Transition'],

        // Bad Request (400)
        InvalidQuantityException::class => ['status' => 400, 'type' => 'validation-failed', 'title' => 'Validation Failed'],
        InvalidLanguageCodeException::class => ['status' => 400, 'type' => 'validation-failed', 'title' => 'Validation Failed'],
        InvalidPaymentMethodException::class => ['status' => 400, 'type' => 'validation-failed', 'title' => 'Validation Failed'],
        UnsupportedPaymentMethodException::class => ['status' => 400, 'type' => 'validation-failed', 'title' => 'Validation Failed'],

        // Unprocessable Entity (422)
        CheckoutValidationException::class => ['status' => 422, 'type' => 'checkout-validation-failed', 'title' => 'Checkout Validation Failed'],

        // Payment errors (402)
        CardDeclinedException::class => ['status' => 402, 'type' => 'payment-failed', 'title' => 'Payment Failed'],
        InsufficientFundsException::class => ['status' => 402, 'type' => 'payment-failed', 'title' => 'Payment Failed'],
    ];

    public function __construct(
        private LoggerInterface $logger,
        private string $environment = 'prod',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Priority -64: after security listeners but before API Platform's error listener (-96)
            KernelEvents::EXCEPTION => ['onKernelException', -64],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        // Only handle API requests
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $throwable = $event->getThrowable();
        $problem = $this->buildProblemDetails($throwable, $request->getPathInfo());

        $this->logException($throwable, $problem);

        $response = new JsonResponse(
            $problem,
            $problem['status'],
            ['Content-Type' => 'application/problem+json']
        );

        $event->setResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProblemDetails(\Throwable $throwable, string $path): array
    {
        $exceptionClass = $throwable::class;

        // Check domain exception map first
        if (isset(self::DOMAIN_EXCEPTION_MAP[$exceptionClass])) {
            $mapping = self::DOMAIN_EXCEPTION_MAP[$exceptionClass];

            $problem = [
                'type' => self::PROBLEM_BASE_URI.'/'.$mapping['type'],
                'title' => $mapping['title'],
                'status' => $mapping['status'],
                'detail' => $throwable->getMessage(),
                'instance' => $path,
            ];

            // Enrich payment gateway exceptions
            if ($throwable instanceof PaymentGatewayException) {
                $problem['gateway_error_code'] = $throwable->getGatewayErrorCode();
                $problem['retryable'] = $throwable->isRetryable();
                $problem['detail'] = $throwable->getUserMessage();
            }

            return $problem;
        }

        // Handle Symfony HTTP exceptions
        if ($throwable instanceof HttpExceptionInterface) {
            return $this->buildFromHttpException($throwable, $path);
        }

        // Handle generic PaymentGatewayException (not mapped specifically)
        if ($throwable instanceof PaymentGatewayException) {
            return [
                'type' => self::PROBLEM_BASE_URI.'/payment-failed',
                'title' => 'Payment Failed',
                'status' => 402,
                'detail' => $throwable->getUserMessage(),
                'instance' => $path,
                'gateway_error_code' => $throwable->getGatewayErrorCode(),
                'retryable' => $throwable->isRetryable(),
            ];
        }

        // Handle generic DomainException
        if ($throwable instanceof \DomainException) {
            return [
                'type' => self::PROBLEM_BASE_URI.'/domain-error',
                'title' => 'Domain Error',
                'status' => 400,
                'detail' => $throwable->getMessage(),
                'instance' => $path,
            ];
        }

        // Handle InvalidArgumentException (validation errors from processors/handlers)
        if ($throwable instanceof \InvalidArgumentException) {
            return [
                'type' => self::PROBLEM_BASE_URI.'/validation-failed',
                'title' => 'Validation Failed',
                'status' => 400,
                'detail' => $throwable->getMessage(),
                'instance' => $path,
            ];
        }

        // Handle generic RuntimeException (often domain "not found" errors)
        if ($throwable instanceof \RuntimeException && !$throwable instanceof HttpExceptionInterface) {
            return [
                'type' => self::PROBLEM_BASE_URI.'/runtime-error',
                'title' => 'Runtime Error',
                'status' => 400,
                'detail' => $throwable->getMessage(),
                'instance' => $path,
            ];
        }

        // Default: Internal Server Error — hide details in production
        return [
            'type' => self::PROBLEM_BASE_URI.'/internal-error',
            'title' => 'Internal Server Error',
            'status' => 500,
            'detail' => 'prod' === $this->environment
                ? 'An unexpected error occurred. Please try again later.'
                : $throwable->getMessage(),
            'instance' => $path,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFromHttpException(HttpExceptionInterface $exception, string $path): array
    {
        $status = $exception->getStatusCode();

        $typeMap = [
            400 => 'bad-request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'resource-not-found',
            405 => 'method-not-allowed',
            409 => 'resource-conflict',
            422 => 'validation-failed',
            429 => 'rate-limit-exceeded',
        ];

        $titleMap = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Resource Not Found',
            405 => 'Method Not Allowed',
            409 => 'Resource Conflict',
            422 => 'Validation Failed',
            429 => 'Too Many Requests',
        ];

        $type = $typeMap[$status] ?? 'http-error';
        $title = $titleMap[$status] ?? 'HTTP Error';

        return [
            'type' => self::PROBLEM_BASE_URI.'/'.$type,
            'title' => $title,
            'status' => $status,
            'detail' => $exception->getMessage() ?: $title,
            'instance' => $path,
        ];
    }

    /**
     * @param array<string, mixed> $problem
     */
    private function logException(\Throwable $throwable, array $problem): void
    {
        $context = [
            'exception_class' => $throwable::class,
            'status' => $problem['status'],
            'instance' => $problem['instance'],
        ];

        if ($problem['status'] >= 500) {
            $this->logger->error('API error: '.$throwable->getMessage(), $context + [
                'trace' => $throwable->getTraceAsString(),
            ]);
        } elseif ($problem['status'] >= 400) {
            $this->logger->warning('API client error: '.$throwable->getMessage(), $context);
        }
    }
}
