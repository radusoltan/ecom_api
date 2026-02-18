<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Service;

use App\Customer\Application\Service\DataExportService;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class DataExportServiceTest extends TestCase
{
    private DataExportService $service;
    private CustomerRepositoryInterface $customerRepository;
    private OrderRepositoryInterface $orderRepository;
    private string $exportDirectory;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->exportDirectory = sys_get_temp_dir().'/test_exports';

        $this->service = new DataExportService(
            $this->customerRepository,
            $this->orderRepository,
            $this->exportDirectory
        );
    }

    protected function tearDown(): void
    {
        // Clean up test export directory
        if (is_dir($this->exportDirectory)) {
            array_map('unlink', glob($this->exportDirectory.'/*'));
            rmdir($this->exportDirectory);
        }
    }

    public function testCollectCustomerData(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('customer@example.com'),
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->customerRepository->expects($this->once())
            ->method('findById')
            ->with($customerId, $tenantId)
            ->willReturn($customer);

        $this->orderRepository->expects($this->once())
            ->method('findByCustomerEmail')
            ->with('customer@example.com', $tenantId)
            ->willReturn([]);

        $data = $this->service->collectCustomerData($customerId, $tenantId);

        self::assertArrayHasKey('profile', $data);
        self::assertArrayHasKey('addresses', $data);
        self::assertArrayHasKey('preferences', $data);
        self::assertArrayHasKey('loyalty', $data);
        self::assertArrayHasKey('orders', $data);
    }

    public function testCollectDataIncludesProfile(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('jane@example.com'),
            firstName: 'Jane',
            lastName: 'Smith'
        );

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->orderRepository->method('findByCustomerEmail')->willReturn([]);

        $data = $this->service->collectCustomerData($customerId, $tenantId);

        self::assertEquals($customerId->toString(), $data['profile']['id']);
        self::assertEquals('jane@example.com', $data['profile']['email']);
        self::assertEquals('Jane', $data['profile']['first_name']);
        self::assertEquals('Smith', $data['profile']['last_name']);
        self::assertEquals('regular', $data['profile']['segment']); // Default segment
        self::assertTrue($data['profile']['is_active']);
    }

    public function testCollectDataIncludesAddresses(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('customer@example.com'),
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->orderRepository->method('findByCustomerEmail')->willReturn([]);

        $data = $this->service->collectCustomerData($customerId, $tenantId);

        self::assertIsArray($data['addresses']);
    }

    public function testCollectDataIncludesPreferences(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('customer@example.com'),
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->orderRepository->method('findByCustomerEmail')->willReturn([]);

        $data = $this->service->collectCustomerData($customerId, $tenantId);

        self::assertIsArray($data['preferences']);
    }

    public function testCollectDataIncludesLoyalty(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('customer@example.com'),
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->orderRepository->method('findByCustomerEmail')->willReturn([]);

        $data = $this->service->collectCustomerData($customerId, $tenantId);

        self::assertArrayHasKey('loyalty', $data);
        self::assertArrayHasKey('current_points', $data['loyalty']);
        self::assertArrayHasKey('segment', $data['loyalty']);
    }

    public function testCollectDataThrowsWhenCustomerNotFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $this->customerRepository->expects($this->once())
            ->method('findById')
            ->with($customerId, $tenantId)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Customer');

        $this->service->collectCustomerData($customerId, $tenantId);
    }

    public function testGenerateJsonExport(): void
    {
        $data = [
            'profile' => [
                'id' => 'test-id-123',
                'email' => 'test@example.com',
            ],
        ];

        $json = $this->service->generateJsonExport($data);

        self::assertJson($json);
        $decoded = json_decode($json, true);
        self::assertArrayHasKey('export_metadata', $decoded);
        self::assertArrayHasKey('customer_data', $decoded);
    }

    public function testGenerateJsonExportIncludesMetadata(): void
    {
        $data = [
            'profile' => [
                'id' => 'customer-456',
                'email' => 'user@example.com',
            ],
        ];

        $json = $this->service->generateJsonExport($data);
        $decoded = json_decode($json, true);

        self::assertArrayHasKey('export_date', $decoded['export_metadata']);
        self::assertArrayHasKey('customer_id', $decoded['export_metadata']);
        self::assertArrayHasKey('gdpr_notice', $decoded['export_metadata']);
        self::assertArrayHasKey('data_retention', $decoded['export_metadata']);
        self::assertEquals('customer-456', $decoded['export_metadata']['customer_id']);
    }

    public function testGenerateDownloadToken(): void
    {
        $token = $this->service->generateDownloadToken();

        self::assertIsString($token);
        self::assertEquals(64, strlen($token)); // 32 bytes = 64 hex characters
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testGenerateDownloadTokenIsUnique(): void
    {
        $token1 = $this->service->generateDownloadToken();
        $token2 = $this->service->generateDownloadToken();

        self::assertNotEquals($token1, $token2);
    }

    public function testGetExportFilePath(): void
    {
        $requestId = DataExportRequestId::generate();

        $filePath = $this->service->getExportFilePath($requestId);

        self::assertStringContainsString($requestId->toString(), $filePath);
        self::assertStringEndsWith('.json', $filePath);
        self::assertDirectoryExists($this->exportDirectory);
    }

    public function testStoreExportFile(): void
    {
        $filePath = $this->exportDirectory.'/test-export.json';
        $content = '{"test": "data"}';

        // Ensure directory exists
        if (!is_dir($this->exportDirectory)) {
            mkdir($this->exportDirectory, 0755, true);
        }

        $this->service->storeExportFile($filePath, $content);

        self::assertFileExists($filePath);
        self::assertEquals($content, file_get_contents($filePath));
    }

    public function testStoreExportFileThrowsOnFailure(): void
    {
        $invalidPath = '/invalid/path/that/does/not/exist/file.json';
        $content = '{"test": "data"}';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to write export file');

        $this->service->storeExportFile($invalidPath, $content);
    }
}
