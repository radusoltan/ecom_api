<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Controller;

use App\Catalog\Infrastructure\Controller\BundleController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(BundleController::class)]
final class BundleControllerTest extends TestCase
{
    private const PRODUCT_ID = '00000000-0000-4000-8000-000000000001';

    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private BundleController $controller;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);

        $this->controller = new BundleController($this->commandBus, $this->queryBus);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);
    }

    // -----------------------------------------------------------------------
    // get()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsBundleDataWhenFound(): void
    {
        $bundleData = ['items' => [], 'discountPercentage' => 10.0];
        $stamp = new HandledStamp($bundleData, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);

        $this->queryBus->method('dispatch')->willReturn($envelope);

        $response = $this->controller->get(self::PRODUCT_ID);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('items', $data);
    }

    #[Test]
    public function itReturns404WhenBundleNotFound(): void
    {
        $envelope = new Envelope(new \stdClass(), []);
        $this->queryBus->method('dispatch')->willReturn($envelope);

        $response = $this->controller->get(self::PRODUCT_ID);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('error', $data);
    }

    #[Test]
    public function itReturns400OnDomainExceptionInGet(): void
    {
        $this->queryBus->method('dispatch')->willThrowException(
            new \DomainException('Product not found')
        );

        $response = $this->controller->get(self::PRODUCT_ID);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('Product not found', $data['error']);
    }

    #[Test]
    public function itReturns500OnGenericExceptionInGet(): void
    {
        $this->queryBus->method('dispatch')->willThrowException(
            new \RuntimeException('DB error')
        );

        $response = $this->controller->get(self::PRODUCT_ID);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('error', $data);
    }

    // -----------------------------------------------------------------------
    // create()
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesBundleSuccessfully(): void
    {
        $envelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->commandBus->method('dispatch')->willReturn($envelope);

        $request = new Request([], [], [], [], [], [], json_encode([
            'items' => [['productId' => self::PRODUCT_ID, 'quantity' => 2]],
            'discountPercentage' => 10.0,
        ]));

        $response = $this->controller->create(self::PRODUCT_ID, $request);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('Bundle created successfully', $data['message']);
    }

    #[Test]
    public function itReturns400WhenItemsMissingOnCreate(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode(['discountPercentage' => 5.0]));

        $response = $this->controller->create(self::PRODUCT_ID, $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('Items array is required', $data['error']);
    }

    #[Test]
    public function itReturns400WhenItemsNotArrayOnCreate(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode(['items' => 'not-array']));

        $response = $this->controller->create(self::PRODUCT_ID, $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function itReturns400OnDomainExceptionInCreate(): void
    {
        $this->commandBus->method('dispatch')->willThrowException(
            new \DomainException('Bundle already exists')
        );

        $request = new Request([], [], [], [], [], [], json_encode([
            'items' => [['productId' => self::PRODUCT_ID, 'quantity' => 1]],
        ]));

        $response = $this->controller->create(self::PRODUCT_ID, $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('Bundle already exists', $data['error']);
    }

    #[Test]
    public function itReturns500OnGenericExceptionInCreate(): void
    {
        $this->commandBus->method('dispatch')->willThrowException(
            new \RuntimeException('DB error')
        );

        $request = new Request([], [], [], [], [], [], json_encode([
            'items' => [['productId' => self::PRODUCT_ID, 'quantity' => 1]],
        ]));

        $response = $this->controller->create(self::PRODUCT_ID, $request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    #[Test]
    public function itCreatesBundleWithDefaultDiscountWhenNotProvided(): void
    {
        $envelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->commandBus->method('dispatch')->willReturn($envelope);

        $request = new Request([], [], [], [], [], [], json_encode([
            'items' => [['productId' => self::PRODUCT_ID, 'quantity' => 1]],
        ]));

        $response = $this->controller->create(self::PRODUCT_ID, $request);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // update()
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesBundleSuccessfully(): void
    {
        $envelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->commandBus->method('dispatch')->willReturn($envelope);

        $request = new Request([], [], [], [], [], [], json_encode([
            'items' => [['productId' => self::PRODUCT_ID, 'quantity' => 3]],
            'discountPercentage' => 15.0,
        ]));

        $response = $this->controller->update(self::PRODUCT_ID, $request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('Bundle updated successfully', $data['message']);
    }

    #[Test]
    public function itReturns400WhenItemsMissingOnUpdate(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode(['discountPercentage' => 5.0]));

        $response = $this->controller->update(self::PRODUCT_ID, $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('Items array is required', $data['error']);
    }

    #[Test]
    public function itReturns400OnDomainExceptionInUpdate(): void
    {
        $this->commandBus->method('dispatch')->willThrowException(
            new \DomainException('Bundle not found')
        );

        $request = new Request([], [], [], [], [], [], json_encode([
            'items' => [['productId' => self::PRODUCT_ID, 'quantity' => 1]],
        ]));

        $response = $this->controller->update(self::PRODUCT_ID, $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function itReturns500OnGenericExceptionInUpdate(): void
    {
        $this->commandBus->method('dispatch')->willThrowException(
            new \RuntimeException('Unexpected error')
        );

        $request = new Request([], [], [], [], [], [], json_encode([
            'items' => [['productId' => self::PRODUCT_ID, 'quantity' => 1]],
        ]));

        $response = $this->controller->update(self::PRODUCT_ID, $request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // delete()
    // -----------------------------------------------------------------------

    #[Test]
    public function itDeletesBundleSuccessfully(): void
    {
        $envelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->commandBus->method('dispatch')->willReturn($envelope);

        $response = $this->controller->delete(self::PRODUCT_ID);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('Bundle removed successfully', $data['message']);
    }

    #[Test]
    public function itReturns400OnDomainExceptionInDelete(): void
    {
        $this->commandBus->method('dispatch')->willThrowException(
            new \DomainException('Bundle does not exist')
        );

        $response = $this->controller->delete(self::PRODUCT_ID);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('Bundle does not exist', $data['error']);
    }

    #[Test]
    public function itReturns500OnGenericExceptionInDelete(): void
    {
        $this->commandBus->method('dispatch')->willThrowException(
            new \RuntimeException('DB failure')
        );

        $response = $this->controller->delete(self::PRODUCT_ID);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('error', $data);
    }
}
