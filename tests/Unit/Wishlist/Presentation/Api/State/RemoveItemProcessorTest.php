<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use App\Wishlist\Presentation\Api\State\RemoveItemProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(RemoveItemProcessor::class)]
final class RemoveItemProcessorTest extends TestCase
{
    private MessageBusInterface&MockObject $commandBus;
    private RequestStack&MockObject $requestStack;
    private RemoveItemProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->processor = new RemoveItemProcessor($this->commandBus, $this->requestStack);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createRequestWithHeaders(
        ?string $tenantId = '00000000-0000-4000-8000-000000000001',
        ?string $customerId = '00000000-0000-4000-8000-000000000010',
    ): Request {
        $request = Request::create('/api/wishlist/items/product-id', 'DELETE');
        if (null !== $tenantId) {
            $request->headers->set('X-Tenant-ID', $tenantId);
        }
        if (null !== $customerId) {
            $request->headers->set('X-Customer-ID', $customerId);
        }

        return $request;
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function processDispatchesRemoveItemCommand(): void
    {
        $request = $this->createRequestWithHeaders();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createMock(Operation::class);

        $this->commandBus->expects(self::once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->processor->process(
            new \stdClass(),
            $operation,
            ['productId' => '00000000-0000-4000-8000-000000000020'],
        );
    }

    // -----------------------------------------------------------------------
    // Missing tenant
    // -----------------------------------------------------------------------

    #[Test]
    public function processThrowsWhenNoTenantId(): void
    {
        $request = $this->createRequestWithHeaders(tenantId: null);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createMock(Operation::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('X-Tenant-ID header is required');

        $this->processor->process(
            new \stdClass(),
            $operation,
            ['productId' => '00000000-0000-4000-8000-000000000020'],
        );
    }

    // -----------------------------------------------------------------------
    // Missing productId
    // -----------------------------------------------------------------------

    #[Test]
    public function processThrowsWhenNoProductId(): void
    {
        $request = $this->createRequestWithHeaders();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createMock(Operation::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('productId is required');

        $this->processor->process(new \stdClass(), $operation, []);
    }

    // -----------------------------------------------------------------------
    // Guest customer
    // -----------------------------------------------------------------------

    #[Test]
    public function processUsesGuestWhenNoCustomerIdHeader(): void
    {
        $request = $this->createRequestWithHeaders(customerId: null);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $operation = $this->createMock(Operation::class);

        $this->commandBus->expects(self::once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->processor->process(
            new \stdClass(),
            $operation,
            ['productId' => '00000000-0000-4000-8000-000000000020'],
        );
    }
}
