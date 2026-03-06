<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use App\Cart\Application\Service\CartToOrderConverter;
use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Cart\Presentation\Api\Processor\CheckoutProcessor;
use App\Cart\Presentation\Api\Resource\CheckoutResource;
use App\Order\Application\Command\PlaceOrderCommand;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\ValueObject\OrderProductId;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(CheckoutProcessor::class)]
#[Group('cart')]
final class CheckoutProcessorTest extends TestCase
{
    private CartRepositoryInterface $cartRepository;
    private CartToOrderConverter $converter;
    private MessageBusInterface $commandBus;
    private TenantContext $tenantContext;
    private LoggerInterface $logger;
    private CheckoutProcessor $processor;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->converter = $this->createMock(CartToOrderConverter::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContext::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->processor = new CheckoutProcessor(
            cartRepository: $this->cartRepository,
            cartToOrderConverter: $this->converter,
            commandBus: $this->commandBus,
            tenantContext: $this->tenantContext,
            logger: $this->logger,
        );
    }

    // -------------------------------------------------------
    // process() — validation guards
    // -------------------------------------------------------

    #[Test]
    public function itThrowsBadRequestWhenDataIsNotCheckoutResource(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Expected CheckoutResource');

        $this->processor->process(new \stdClass(), new Post());
    }

    #[Test]
    public function itThrowsBadRequestWhenCartIdIsEmpty(): void
    {
        $resource = new CheckoutResource();
        $resource->cartId = '';
        $resource->customerEmail = 'test@example.com';
        $resource->billingAddress = ['street' => '1', 'city' => 'A', 'state' => 'B', 'postalCode' => '1', 'country' => 'US'];
        $resource->useBillingAsShipping = true;

        $this->expectException(BadRequestHttpException::class);

        $this->processor->process($resource, new Post());
    }

    #[Test]
    public function itThrowsBadRequestWhenEmailIsInvalid(): void
    {
        $resource = new CheckoutResource();
        $resource->cartId = CartId::generate()->toString();
        $resource->customerEmail = 'not-an-email';
        $resource->billingAddress = ['street' => '1', 'city' => 'A', 'state' => 'B', 'postalCode' => '1', 'country' => 'US'];
        $resource->useBillingAsShipping = true;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid customer email format');

        $this->processor->process($resource, new Post());
    }

    #[Test]
    public function itThrowsBadRequestWhenTenantContextIsMissing(): void
    {
        $resource = new CheckoutResource();
        $resource->cartId = CartId::generate()->toString();
        $resource->customerEmail = 'customer@example.com';
        $resource->billingAddress = ['street' => '1', 'city' => 'A', 'state' => 'B', 'postalCode' => '1', 'country' => 'US'];
        $resource->useBillingAsShipping = true;

        $this->tenantContext->expects(self::once())
            ->method('getCurrentTenantId')
            ->willReturn(null);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant context is required');

        $this->processor->process($resource, new Post());
    }

    #[Test]
    public function itThrowsNotFoundWhenCartDoesNotExist(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $cartId = CartId::generate()->toString();

        $resource = new CheckoutResource();
        $resource->cartId = $cartId;
        $resource->customerEmail = 'customer@example.com';
        $resource->billingAddress = ['street' => '1', 'city' => 'A', 'state' => 'B', 'postalCode' => '1', 'country' => 'US'];
        $resource->useBillingAsShipping = true;

        $this->tenantContext->expects(self::once())
            ->method('getCurrentTenantId')
            ->willReturn($tenantId);

        $this->cartRepository->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($resource, new Post());
    }

    #[Test]
    public function itSucceedsAndReturnsCheckoutResourceWithOrderDetails(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $cartIdStr = CartId::generate()->toString();

        $resource = new CheckoutResource();
        $resource->cartId = $cartIdStr;
        $resource->customerEmail = 'customer@example.com';
        $resource->billingAddress = [
            'street' => '123 Main St', 'city' => 'NYC', 'state' => 'NY',
            'postalCode' => '10001', 'country' => 'US',
        ];
        $resource->useBillingAsShipping = true;

        // Cart mock — active, non-empty, belongs to tenant
        $cart = $this->createMock(Cart::class);
        $cart->method('tenantId')->willReturn($tenantId);
        $cart->method('isActive')->willReturn(true);
        $cart->method('isEmpty')->willReturn(false);
        $cart->method('getItemCount')->willReturn(1);
        $cart->method('id')->willReturn(CartId::fromString($cartIdStr));

        // Build a real Order aggregate for the handler result
        $address = Address::create('123 Main St', 'NYC', 'NY', '10001', 'US');
        $line = OrderLine::create(
            OrderProductId::generate(),
            'Test Product',
            1,
            Money::fromScalars(1000, 'USD')
        );
        $order = Order::place(
            id: OrderId::generate(),
            tenantId: $tenantId,
            customerEmail: 'customer@example.com',
            lines: [$line],
            shippingAddress: $address,
            billingAddress: $address,
        );

        $command = $this->createMock(PlaceOrderCommand::class);
        $command->orderId = OrderId::generate()->toString();

        $this->tenantContext->expects(self::once())
            ->method('getCurrentTenantId')
            ->willReturn($tenantId);

        $this->cartRepository->expects(self::once())
            ->method('findById')
            ->willReturn($cart);

        $this->converter->expects(self::once())
            ->method('convert')
            ->willReturn($command);

        $stamp = new HandledStamp($order, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);

        $this->commandBus->expects(self::once())
            ->method('dispatch')
            ->willReturn($envelope);

        $this->tenantContext->expects(self::once())
            ->method('setCurrentTenant')
            ->with($tenantId);

        $this->cartRepository->expects(self::once())
            ->method('markAsConverted');

        $result = $this->processor->process($resource, new Post());

        self::assertInstanceOf(CheckoutResource::class, $result);
        self::assertSame('customer@example.com', $result->customerEmail);
        self::assertSame(1000, $result->totalAmount);
    }
}
