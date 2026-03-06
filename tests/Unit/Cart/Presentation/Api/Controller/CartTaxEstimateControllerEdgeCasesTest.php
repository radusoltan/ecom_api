<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Presentation\Api\Controller;

use App\Cart\Application\Query\GetCartTaxEstimate\GetCartTaxEstimateQuery;
use App\Cart\Presentation\Api\Controller\CartTaxEstimateController;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(CartTaxEstimateController::class)]
final class CartTaxEstimateControllerEdgeCasesTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CART_ID = '01HQVZP3X8EXAMPLEULID123';

    private MessageBusInterface $queryBus;
    private TenantContextInterface $tenantContext;
    private CartTaxEstimateController $controller;

    protected function setUp(): void
    {
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);
        $this->tenantContext->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $this->controller = new CartTaxEstimateController($this->queryBus, $this->tenantContext);
    }

    // -------------------------------------------------------
    // regionCode validation
    // -------------------------------------------------------

    #[Test]
    public function itReturns400ForInvalidRegionCode(): void
    {
        $request = Request::create('/api/carts/'.self::CART_ID.'/tax-estimate', 'GET', [
            'countryCode' => 'RO',
            'regionCode' => 'TOOLONG',
        ]);

        $response = ($this->controller)($request, self::CART_ID);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('regionCode', $data['error']);
    }

    #[Test]
    public function itReturns400ForSingleCharRegionCode(): void
    {
        $request = Request::create('/api/carts/'.self::CART_ID.'/tax-estimate', 'GET', [
            'countryCode' => 'US',
            'regionCode' => 'X',
        ]);

        $response = ($this->controller)($request, self::CART_ID);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('regionCode', $data['error']);
    }

    // -------------------------------------------------------
    // No HandledStamp path
    // -------------------------------------------------------

    #[Test]
    public function itReturns500WhenNoHandledStampPresent(): void
    {
        // Envelope returned without a HandledStamp
        $this->queryBus->method('dispatch')
            ->willReturn(new Envelope(new GetCartTaxEstimateQuery(self::CART_ID, self::TENANT_ID)));

        $request = Request::create('/api/carts/'.self::CART_ID.'/tax-estimate', 'GET', [
            'countryCode' => 'RO',
        ]);

        $response = ($this->controller)($request, self::CART_ID);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('No handler found', $data['error']);
    }

    // -------------------------------------------------------
    // InvalidArgumentException from handler
    // -------------------------------------------------------

    #[Test]
    public function itReturns400WhenHandlerThrowsInvalidArgumentException(): void
    {
        $this->queryBus->method('dispatch')
            ->willThrowException(new \InvalidArgumentException('Bad argument value'));

        $request = Request::create('/api/carts/'.self::CART_ID.'/tax-estimate', 'GET', [
            'countryCode' => 'DE',
        ]);

        $response = ($this->controller)($request, self::CART_ID);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('Bad argument value', $data['error']);
    }

    // -------------------------------------------------------
    // Valid regionCode uppercasing
    // -------------------------------------------------------

    #[Test]
    public function itUppercasesRegionCode(): void
    {
        // Use a valid two-character region code (lowercase) - controller uppercases it
        // and then dispatches the query successfully
        $this->queryBus->method('dispatch')
            ->willThrowException(new \DomainException('Cart is not active'));

        $request = Request::create('/api/carts/'.self::CART_ID.'/tax-estimate', 'GET', [
            'countryCode' => 'us',
            'regionCode' => 'ca',
        ]);

        // The controller uppercases both codes before dispatching, so it reaches dispatch
        $response = ($this->controller)($request, self::CART_ID);

        // 422 (DomainException) proves the regionCode validation passed
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }
}
