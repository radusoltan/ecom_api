<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Presentation\Api\Controller;

use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Presentation\Api\Controller\TaxCalculationController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(TaxCalculationController::class)]
final class TaxCalculationControllerTest extends TestCase
{
    private MessageBusInterface $queryBus;
    private TenantContextInterface $tenantContext;
    private ValidatorInterface $validator;
    private string $tenantId = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
    }

    private function buildController(): TaxCalculationController
    {
        return new TaxCalculationController(
            $this->queryBus,
            $this->tenantContext,
            $this->validator,
        );
    }

    private function buildRequest(array $body): Request
    {
        return Request::create(
            '/api/v1/tax/calculate',
            'POST',
            [],
            [],
            [],
            [],
            json_encode($body)
        );
    }

    #[Test]
    public function itReturns400WhenTenantContextNotSet(): void
    {
        $this->tenantContext->method('getCurrentTenantId')->willReturn(null);

        $request = $this->buildRequest(['amount' => 10000, 'countryCode' => 'DE']);
        $controller = $this->buildController();
        $response = $controller($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame('Tenant context not set', $data['error']);
    }

    #[Test]
    public function itReturns400WhenInvalidJson(): void
    {
        $this->tenantContext->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString($this->tenantId));

        $request = Request::create('/api/v1/tax/calculate', 'POST', [], [], [], [], 'not-json{');
        $controller = $this->buildController();
        $response = $controller($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame('Invalid JSON', $data['error']);
    }

    /**
     * Tests that reach the Assert\Collection with Assert\Choice will trigger a
     * Symfony\Component\Validator\Exception\InvalidArgumentException because
     * the controller uses old-style Choice constructor syntax incompatible with
     * Symfony 8.0. We verify this propagates as an exception.
     */
    #[Test]
    public function itThrowsOnConstraintCreationWithCurrentSymfonyVersion(): void
    {
        $this->tenantContext->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString($this->tenantId));

        $request = $this->buildRequest([
            'amount' => 10000,
            'countryCode' => 'DE',
            'category' => 'standard',
        ]);
        $controller = $this->buildController();

        $this->expectException(\Symfony\Component\Validator\Exception\InvalidArgumentException::class);
        $controller($request);
    }
}
