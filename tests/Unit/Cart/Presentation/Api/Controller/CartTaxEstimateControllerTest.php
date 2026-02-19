<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Presentation\Api\Controller;

use App\Cart\Application\DTO\CartTaxEstimateDTO;
use App\Cart\Application\Query\GetCartTaxEstimate\GetCartTaxEstimateQuery;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Presentation\Api\Controller\CartTaxEstimateController;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class CartTaxEstimateControllerTest extends TestCase
{
    private MessageBusInterface $queryBus;
    private TenantContextInterface $tenantContext;
    private CartTaxEstimateController $controller;

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CART_ID = '01HQVZP3X8EXAMPLEULID123';

    protected function setUp(): void
    {
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);
        $this->controller = new CartTaxEstimateController($this->queryBus, $this->tenantContext);
    }

    public function testItReturnsTaxEstimateWithQueryParams(): void
    {
        $this->tenantContext->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $dto = new CartTaxEstimateDTO(
            cartId: self::CART_ID,
            subtotal: 5998,
            currency: 'EUR',
            taxEstimateAvailable: true,
            taxAmount: 1140,
            total: 7138,
            taxRate: 19.0,
            jurisdiction: 'RO',
            taxRuleName: 'Romania Standard VAT',
            message: null,
            itemBreakdown: [],
        );

        $stamp = new HandledStamp($dto, 'handler');
        $envelope = new Envelope(new GetCartTaxEstimateQuery(self::CART_ID, self::TENANT_ID, 'RO'), [$stamp]);

        $this->queryBus->method('dispatch')->willReturn($envelope);

        $request = Request::create('/api/carts/'.self::CART_ID.'/tax-estimate', 'GET', [
            'countryCode' => 'RO',
        ]);

        $response = ($this->controller)($request, self::CART_ID);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['taxEstimateAvailable']);
        $this->assertSame(1140, $data['taxAmount']);
        $this->assertSame('RO', $data['jurisdiction']);
    }

    public function testItReturns400WhenTenantContextMissing(): void
    {
        $this->tenantContext->method('getCurrentTenantId')->willReturn(null);

        $request = Request::create('/api/carts/'.self::CART_ID.'/tax-estimate', 'GET');

        $response = ($this->controller)($request, self::CART_ID);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('Tenant', $data['error']);
    }

    public function testItReturns404WhenCartNotFound(): void
    {
        $this->tenantContext->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $this->queryBus->method('dispatch')
            ->willThrowException(CartNotFoundException::withId(self::CART_ID));

        $request = Request::create('/api/carts/'.self::CART_ID.'/tax-estimate', 'GET', [
            'countryCode' => 'RO',
        ]);

        $response = ($this->controller)($request, self::CART_ID);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testItReturnsEstimateUnavailableWhenNoAddress(): void
    {
        $this->tenantContext->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $dto = new CartTaxEstimateDTO(
            cartId: self::CART_ID,
            subtotal: 2999,
            currency: 'EUR',
            taxEstimateAvailable: false,
            taxAmount: 0,
            total: 2999,
            taxRate: 0,
            jurisdiction: null,
            taxRuleName: null,
            message: 'Tax will be calculated at checkout when shipping address is provided',
            itemBreakdown: [],
        );

        $stamp = new HandledStamp($dto, 'handler');
        $envelope = new Envelope(new GetCartTaxEstimateQuery(self::CART_ID, self::TENANT_ID), [$stamp]);

        $this->queryBus->method('dispatch')->willReturn($envelope);

        $request = Request::create('/api/carts/'.self::CART_ID.'/tax-estimate', 'GET');

        $response = ($this->controller)($request, self::CART_ID);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['taxEstimateAvailable']);
        $this->assertSame(0, $data['taxAmount']);
        $this->assertNotNull($data['message']);
    }

    public function testItReturns400ForInvalidCountryCode(): void
    {
        $this->tenantContext->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $request = Request::create('/api/carts/'.self::CART_ID.'/tax-estimate', 'GET', [
            'countryCode' => 'INVALID',
        ]);

        $response = ($this->controller)($request, self::CART_ID);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('countryCode', $data['error']);
    }

    public function testItReturns422ForInactiveCart(): void
    {
        $this->tenantContext->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $this->queryBus->method('dispatch')
            ->willThrowException(new \DomainException('Cart is not active'));

        $request = Request::create('/api/carts/'.self::CART_ID.'/tax-estimate', 'GET', [
            'countryCode' => 'RO',
        ]);

        $response = ($this->controller)($request, self::CART_ID);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }
}
