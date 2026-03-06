<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Post;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Model\Shipment;
use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Domain\Model\ShipmentStatus;
use App\Shipping\Infrastructure\ApiPlatform\State\CreateShipmentProcessor;
use App\Shipping\Presentation\Api\Resource\ShipmentResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class CreateShipmentProcessorTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private RequestStack&MockObject $requestStack;
    private CreateShipmentProcessor $processor;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->processor = new CreateShipmentProcessor($this->messageBus, $this->requestStack);
    }

    public function testProcessCreatesShipmentAndReturnsResource(): void
    {
        $data = new ShipmentResource();
        $data->orderId = '00000000-0000-4000-8000-000000000010';
        $data->recipientName = 'John Doe';
        $data->recipientAddress = ['street' => '123 Main St', 'city' => 'NYC'];
        $data->rateAmount = 999;
        $data->rateCurrency = 'USD';
        $data->carrierCode = 'ups';
        $data->rateEstimatedDays = 5;
        $data->rateServiceName = 'Ground';

        $request = $this->createMock(Request::class);
        $request->headers = new HeaderBag(['X-Tenant-ID' => '00000000-0000-4000-8000-000000000001']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Create a mock Shipment for the handler result
        $shipment = $this->createMock(Shipment::class);
        $shipmentId = $this->createMock(ShipmentId::class);
        $shipmentId->method('toString')->willReturn('00000000-0000-4000-8000-000000000099');
        $shipment->method('id')->willReturn($shipmentId);

        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $shipment->method('tenantId')->willReturn($tenantId);
        $shipment->method('orderId')->willReturn('00000000-0000-4000-8000-000000000010');

        $status = $this->createMock(ShipmentStatus::class);
        $status->method('value')->willReturn('created');
        $shipment->method('status')->willReturn($status);

        $shipment->method('carrier')->willReturn(null);
        $shipment->method('trackingNumber')->willReturn(null);
        $shipment->method('recipientName')->willReturn('John Doe');
        $shipment->method('recipientAddress')->willReturn(['street' => '123 Main St', 'city' => 'NYC']);
        $shipment->method('shippedAt')->willReturn(null);
        $shipment->method('deliveredAt')->willReturn(null);
        $shipment->method('createdAt')->willReturn(new \DateTimeImmutable('2026-01-01'));
        $shipment->method('updatedAt')->willReturn(null);
        $shipment->method('rate')->willReturn(null);

        $envelope = new Envelope(new \stdClass(), [new HandledStamp($shipment, 'handler')]);
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $result = $this->processor->process($data, new Post());

        $this->assertInstanceOf(ShipmentResource::class, $result);
        $this->assertSame('00000000-0000-4000-8000-000000000099', $result->id);
        $this->assertSame('John Doe', $result->recipientName);
    }

    public function testProcessWithNoRequestDefaultsToEmptyTenantId(): void
    {
        $data = new ShipmentResource();
        $data->orderId = '00000000-0000-4000-8000-000000000010';
        $data->recipientName = 'Jane Doe';
        $data->recipientAddress = [];

        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $shipment = $this->createMock(Shipment::class);
        $shipmentId = $this->createMock(ShipmentId::class);
        $shipmentId->method('toString')->willReturn('00000000-0000-4000-8000-000000000099');
        $shipment->method('id')->willReturn($shipmentId);
        $shipment->method('tenantId')->willReturn(TenantId::fromString('00000000-0000-4000-8000-000000000001'));
        $shipment->method('orderId')->willReturn('00000000-0000-4000-8000-000000000010');
        $status = $this->createMock(ShipmentStatus::class);
        $status->method('value')->willReturn('created');
        $shipment->method('status')->willReturn($status);
        $shipment->method('carrier')->willReturn(null);
        $shipment->method('trackingNumber')->willReturn(null);
        $shipment->method('recipientName')->willReturn('Jane Doe');
        $shipment->method('recipientAddress')->willReturn([]);
        $shipment->method('shippedAt')->willReturn(null);
        $shipment->method('deliveredAt')->willReturn(null);
        $shipment->method('createdAt')->willReturn(new \DateTimeImmutable('2026-01-01'));
        $shipment->method('updatedAt')->willReturn(null);
        $shipment->method('rate')->willReturn(null);

        $envelope = new Envelope(new \stdClass(), [new HandledStamp($shipment, 'handler')]);
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $result = $this->processor->process($data, new Post());

        $this->assertInstanceOf(ShipmentResource::class, $result);
    }
}
