<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Pricing\Application\Query\ValidateCoupon\ValidateCouponQuery;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Presentation\Api\Resource\CouponValidationResource;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class ValidateCouponProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $queryBus
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CouponValidationResource
    {
        assert($data instanceof CouponValidationResource);

        // Validate required fields
        if (null === $data->code || '' === trim($data->code)) {
            throw new BadRequestHttpException('Coupon code is required');
        }

        if (null === $data->cart_total || !isset($data->cart_total['amount'], $data->cart_total['currency'])) {
            throw new BadRequestHttpException('Cart total with amount and currency is required');
        }

        // Get tenant ID from context (set by TenantContextProvider)
        $tenantId = $context['tenant_id'] ?? null;
        if (null === $tenantId) {
            throw new \RuntimeException('Tenant ID not found in context');
        }

        try {
            // Build value objects
            $couponCode = CouponCode::fromString($data->code);
            $cartTotal = Money::of($data->cart_total['amount'], $data->cart_total['currency']);
            $tenantIdVO = TenantId::fromString($tenantId);

            // Create and dispatch query
            $query = new ValidateCouponQuery(
                couponCode: $couponCode,
                cartTotal: $cartTotal,
                tenantId: $tenantIdVO
            );

            $envelope = $this->queryBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);

            if (!$handledStamp instanceof HandledStamp) {
                throw new \RuntimeException('No handler found for query');
            }

            $result = $handledStamp->getResult();

            // Build response resource
            $response = new CouponValidationResource();
            $response->valid = $result->valid;
            $response->message = $result->message;
            $response->discount_amount = $result->discountAmount;

            if ($result->promotion) {
                $response->promotion = [
                    'id' => $result->promotion->id,
                    'name' => $result->promotion->name,
                    'type' => $result->promotion->type,
                    'discount_type' => $result->promotion->discountType,
                    'discount_value' => $result->promotion->discountValue,
                    'priority' => $result->promotion->priority,
                ];
            }

            return $response;
        } catch (HandlerFailedException $exception) {
            $previous = $exception->getPrevious();
            if ($previous instanceof HttpExceptionInterface) {
                throw $previous;
            }

            throw $exception;
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }
    }
}
