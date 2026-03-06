<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Controller;

use App\Catalog\Infrastructure\Controller\ProductOptionsController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ProductOptionsController::class)]
final class ProductOptionsControllerTest extends TestCase
{
    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private ProductOptionsController $controller;
    private string $tenantId = '00000000-0000-4000-8000-000000000001';
    private string $productId = '00000000-0000-4000-8000-000000000002';

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);

        $this->controller = new ProductOptionsController($this->commandBus, $this->queryBus);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);
    }

    // ---------------------------------------------------------------
    // getOptions
    // ---------------------------------------------------------------

    #[Test]
    public function getOptionsReturns400WhenTenantHeaderMissing(): void
    {
        $request = Request::create('/api/v1/products/'.$this->productId.'/options', 'GET');
        $response = $this->controller->getOptions($this->productId, $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function getOptionsReturnsSuccess(): void
    {
        $request = Request::create('/api/v1/products/'.$this->productId.'/options', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $result = [['code' => 'color', 'values' => ['red', 'blue']]];
        $stamp = new HandledStamp($result, 'handler');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $this->queryBus->method('dispatch')->willReturn($envelope);

        $response = $this->controller->getOptions($this->productId, $request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function getOptionsReturns400OnInvalidArgument(): void
    {
        $request = Request::create('/api/v1/products/invalid/options', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $this->queryBus->method('dispatch')
            ->willThrowException(new \InvalidArgumentException('Invalid UUID'));

        $response = $this->controller->getOptions('invalid', $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function getOptionsReturns500OnGenericException(): void
    {
        $request = Request::create('/api/v1/products/'.$this->productId.'/options', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $this->queryBus->method('dispatch')
            ->willThrowException(new \RuntimeException('DB error'));

        $response = $this->controller->getOptions($this->productId, $request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    // ---------------------------------------------------------------
    // defineOption
    // ---------------------------------------------------------------

    #[Test]
    public function defineOptionReturns400WhenTenantHeaderMissing(): void
    {
        $request = Request::create('/api/v1/products/'.$this->productId.'/options', 'POST', [], [], [], [], json_encode([]));
        $response = $this->controller->defineOption($this->productId, $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function defineOptionReturns400WhenInvalidJson(): void
    {
        $request = Request::create('/api/v1/products/'.$this->productId.'/options', 'POST', [], [], [], [], 'not-json{');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $response = $this->controller->defineOption($this->productId, $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('Invalid JSON', $data['error']);
    }

    #[Test]
    public function defineOptionReturns400WhenRequiredFieldsMissing(): void
    {
        $request = Request::create('/api/v1/products/'.$this->productId.'/options', 'POST', [], [], [], [], json_encode(['code' => 'color']));
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $response = $this->controller->defineOption($this->productId, $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('nameTranslations', $data['error']);
    }

    #[Test]
    public function defineOptionReturnsCreated(): void
    {
        $body = json_encode([
            'code' => 'color',
            'nameTranslations' => ['en' => 'Color', 'de' => 'Farbe'],
            'position' => 1,
            'values' => [['code' => 'red', 'name' => ['en' => 'Red']]],
        ]);
        $request = Request::create('/api/v1/products/'.$this->productId.'/options', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $envelope = new Envelope(new \stdClass());
        $this->commandBus->method('dispatch')->willReturn($envelope);

        $response = $this->controller->defineOption($this->productId, $request);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('defined successfully', $data['message']);
    }

    #[Test]
    public function defineOptionReturns400OnInvalidArgument(): void
    {
        $body = json_encode(['code' => 'color', 'nameTranslations' => ['en' => 'Color']]);
        $request = Request::create('/api/v1/products/'.$this->productId.'/options', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $this->commandBus->method('dispatch')
            ->willThrowException(new \InvalidArgumentException('Invalid code'));

        $response = $this->controller->defineOption($this->productId, $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function defineOptionReturns409OnDomainException(): void
    {
        $body = json_encode(['code' => 'color', 'nameTranslations' => ['en' => 'Color']]);
        $request = Request::create('/api/v1/products/'.$this->productId.'/options', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $this->commandBus->method('dispatch')
            ->willThrowException(new \DomainException('Option already exists'));

        $response = $this->controller->defineOption($this->productId, $request);

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    #[Test]
    public function defineOptionReturns500OnGenericException(): void
    {
        $body = json_encode(['code' => 'color', 'nameTranslations' => ['en' => 'Color']]);
        $request = Request::create('/api/v1/products/'.$this->productId.'/options', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $this->commandBus->method('dispatch')
            ->willThrowException(new \RuntimeException('DB error'));

        $response = $this->controller->defineOption($this->productId, $request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    // ---------------------------------------------------------------
    // addOptionValue
    // ---------------------------------------------------------------

    #[Test]
    public function addOptionValueReturns400WhenTenantHeaderMissing(): void
    {
        $request = Request::create('/api/v1/products/'.$this->productId.'/options/color/values', 'POST', [], [], [], [], json_encode([]));
        $response = $this->controller->addOptionValue($this->productId, 'color', $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function addOptionValueReturns400WhenInvalidJson(): void
    {
        $request = Request::create('/api/v1/products/'.$this->productId.'/options/color/values', 'POST', [], [], [], [], 'bad{');
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $response = $this->controller->addOptionValue($this->productId, 'color', $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function addOptionValueReturns400WhenRequiredFieldsMissing(): void
    {
        $request = Request::create('/api/v1/products/'.$this->productId.'/options/color/values', 'POST', [], [], [], [], json_encode(['valueCode' => 'red']));
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $response = $this->controller->addOptionValue($this->productId, 'color', $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('nameTranslations', $data['error']);
    }

    #[Test]
    public function addOptionValueReturnsCreated(): void
    {
        $body = json_encode([
            'valueCode' => 'green',
            'nameTranslations' => ['en' => 'Green'],
            'position' => 3,
        ]);
        $request = Request::create('/api/v1/products/'.$this->productId.'/options/color/values', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $envelope = new Envelope(new \stdClass());
        $this->commandBus->method('dispatch')->willReturn($envelope);

        $response = $this->controller->addOptionValue($this->productId, 'color', $request);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    #[Test]
    public function addOptionValueReturns400OnInvalidArgument(): void
    {
        $body = json_encode(['valueCode' => 'red', 'nameTranslations' => ['en' => 'Red']]);
        $request = Request::create('/api/v1/products/'.$this->productId.'/options/color/values', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $this->commandBus->method('dispatch')
            ->willThrowException(new \InvalidArgumentException('Bad code'));

        $response = $this->controller->addOptionValue($this->productId, 'color', $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function addOptionValueReturns404OnDomainException(): void
    {
        $body = json_encode(['valueCode' => 'red', 'nameTranslations' => ['en' => 'Red']]);
        $request = Request::create('/api/v1/products/'.$this->productId.'/options/color/values', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $this->commandBus->method('dispatch')
            ->willThrowException(new \DomainException('Option not found'));

        $response = $this->controller->addOptionValue($this->productId, 'color', $request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function addOptionValueReturns500OnGenericException(): void
    {
        $body = json_encode(['valueCode' => 'red', 'nameTranslations' => ['en' => 'Red']]);
        $request = Request::create('/api/v1/products/'.$this->productId.'/options/color/values', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Tenant-ID', $this->tenantId);

        $this->commandBus->method('dispatch')
            ->willThrowException(new \RuntimeException('DB error'));

        $response = $this->controller->addOptionValue($this->productId, 'color', $request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }
}
