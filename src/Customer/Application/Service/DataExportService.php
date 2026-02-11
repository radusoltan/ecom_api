<?php

declare(strict_types=1);

namespace App\Customer\Application\Service;

use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Data Export Service.
 *
 * Responsible for collecting customer data and generating GDPR-compliant exports.
 *
 * Business Rules:
 * - Exports include all customer PII (GDPR Article 15)
 * - Data is formatted as JSON with metadata
 * - Files are stored securely in var/exports/
 * - Download tokens are cryptographically secure (32 bytes)
 */
final readonly class DataExportService
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private OrderRepositoryInterface $orderRepository,
        private string $exportDirectory
    ) {
    }

    /**
     * Collect all customer data for export.
     *
     * @return array<string, mixed>
     */
    public function collectCustomerData(CustomerId $customerId, TenantId $tenantId): array
    {
        $customer = $this->customerRepository->findById($customerId, $tenantId);

        if (null === $customer) {
            throw new \RuntimeException(sprintf('Customer %s not found', $customerId->toString()));
        }

        // Collect profile data
        $profileData = [
            'id' => $customer->id()->toString(),
            'email' => $customer->email()->toString(),
            'first_name' => $customer->firstName(),
            'last_name' => $customer->lastName(),
            'phone_number' => $customer->phoneNumber(),
            'segment' => $customer->segment()->value(),
            'is_active' => $customer->isActive(),
            'created_at' => $customer->createdAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $customer->updatedAt()->format(\DateTimeInterface::ATOM),
        ];

        // Collect addresses
        $addresses = array_map(
            fn ($address) => $address->toArray(),
            $customer->getAddresses()
        );

        // Collect preferences
        $preferences = $customer->preferences()->toArray();

        // Collect loyalty points
        $loyaltyData = [
            'current_points' => $customer->loyaltyPoints(),
            'segment' => $customer->segment()->value(),
        ];

        // Collect orders (basic info only, not full order details)
        $orders = $this->orderRepository->findByCustomerEmail($customer->email()->toString(), $tenantId);
        $orderSummary = array_map(
            fn ($order) => [
                'id' => $order->id()->toString(),
                'status' => $order->status()->value(),
                'total' => $order->total()->getAmount(),
                'currency' => $order->total()->getCurrency()->getCurrencyCode(),
                'created_at' => $order->createdAt()->format(\DateTimeInterface::ATOM),
            ],
            $orders
        );

        return [
            'profile' => $profileData,
            'addresses' => $addresses,
            'preferences' => $preferences,
            'loyalty' => $loyaltyData,
            'orders' => $orderSummary,
        ];
    }

    /**
     * Generate JSON export with metadata.
     *
     * @param array<string, mixed> $data
     */
    public function generateJsonExport(array $data): string
    {
        $export = [
            'export_metadata' => [
                'export_date' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'customer_id' => $data['profile']['id'] ?? 'unknown',
                'gdpr_notice' => 'This export contains all personal data associated with your account as required by GDPR Article 15.',
                'data_retention' => 'This export file will be available for download for 24 hours.',
            ],
            'customer_data' => $data,
        ];

        $result = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (false === $result) {
            throw new \RuntimeException('Failed to encode data export to JSON');
        }

        return $result;
    }

    /**
     * Generate a secure download token.
     */
    public function generateDownloadToken(): string
    {
        return bin2hex(random_bytes(32)); // 64-character hex string
    }

    /**
     * Get the file path for an export.
     */
    public function getExportFilePath(DataExportRequestId $id): string
    {
        // Ensure export directory exists
        if (!is_dir($this->exportDirectory)) {
            mkdir($this->exportDirectory, 0755, true);
        }

        return sprintf('%s/%s.json', $this->exportDirectory, $id->toString());
    }

    /**
     * Store export data to file.
     */
    public function storeExportFile(string $filePath, string $content): void
    {
        $result = file_put_contents($filePath, $content);

        if (false === $result) {
            throw new \RuntimeException(sprintf('Failed to write export file to %s', $filePath));
        }

        // Set restrictive permissions (owner read/write only)
        chmod($filePath, 0600);
    }
}
