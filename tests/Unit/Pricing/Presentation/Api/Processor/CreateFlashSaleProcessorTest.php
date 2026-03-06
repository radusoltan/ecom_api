<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\FlashSaleEntity;
use App\Pricing\Presentation\Api\Processor\CreateFlashSaleProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class CreateFlashSaleProcessorTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private RequestStack&MockObject $requestStack;
    private CreateFlashSaleProcessor $processor;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->processor = new CreateFlashSaleProcessor($this->messageBus, $this->requestStack);
    }

    public function testProcessSuccessfully(): void
    {
        $data = $this->createMock(FlashSaleEntity::class);
        $data->method('getName')->willReturn('Summer Sale');
        $data->method('getProductIds')->willReturn(['00000000-0000-4000-8000-000000000010']);
        $data->method('getDiscountType')->willReturn('percentage');
        $data->method('getDiscountValue')->willReturn(20.0);
        $data->method('getStartTime')->willReturn(new \DateTimeImmutable('2026-06-01'));
        $data->method('getEndTime')->willReturn(new \DateTimeImmutable('2026-06-30'));

        $request = $this->createMock(Request::class);
        $request->headers = new HeaderBag(['X-Tenant-ID' => '00000000-0000-4000-8000-000000000001']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $result = $this->processor->process($data, new Post());

        $this->assertSame($data, $result);
    }

    public function testThrowsWhenTenantIdHeaderMissing(): void
    {
        $data = $this->createMock(FlashSaleEntity::class);

        $request = $this->createMock(Request::class);
        $request->headers = new HeaderBag();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('X-Tenant-ID header is required');

        $this->processor->process($data, new Post());
    }

    public function testThrowsWhenNoRequestAvailable(): void
    {
        $data = $this->createMock(FlashSaleEntity::class);

        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('X-Tenant-ID header is required');

        $this->processor->process($data, new Post());
    }
}
