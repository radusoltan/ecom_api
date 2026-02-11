<?php

declare(strict_types=1);

namespace App\Customer\Application\Service;

use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Service for exporting customer data to CSV/JSON files.
 *
 * Handles batch processing for large datasets and generates export files
 * in the requested format.
 *
 * Business Rules:
 * - Batch size: 1000 customers per batch for memory efficiency
 * - Exported fields: id, name, email, phone, status, segment, preferredLanguage,
 *   preferredCurrency, createdAt, orderCount, totalSpent
 * - Support for filtering by segment, status, date range
 */
final readonly class CustomerExportService
{
    private const BATCH_SIZE = 1000;

    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {
    }

    /**
     * Export customers to CSV format.
     *
     * @param array<string, mixed> $filters
     */
    public function exportToCsv(TenantId $tenantId, array $filters = []): string
    {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Failed to create temporary file for export');
        }

        try {
            // Write CSV header
            fputcsv($handle, [
                'id',
                'email',
                'first_name',
                'last_name',
                'phone_number',
                'segment',
                'loyalty_points',
                'status',
                'created_at',
                'updated_at',
            ]);

            // Get filtered customers
            $customers = $this->getFilteredCustomers($tenantId, $filters);

            // Write data rows in batches
            foreach ($this->batchCustomers($customers, self::BATCH_SIZE) as $batch) {
                foreach ($batch as $customer) {
                    fputcsv($handle, $this->customerToCsvRow($customer));
                }
            }

            rewind($handle);
            $csv = stream_get_contents($handle);

            if (false === $csv) {
                throw new \RuntimeException('Failed to read CSV content');
            }

            return $csv;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Export customers to JSON format.
     *
     * @param array<string, mixed> $filters
     */
    public function exportToJson(TenantId $tenantId, array $filters = []): string
    {
        $customers = $this->getFilteredCustomers($tenantId, $filters);

        $data = array_map(
            fn (Customer $customer) => $this->customerToArray($customer),
            $customers
        );

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    /**
     * Get CSV template for customer imports.
     */
    public function getCsvTemplate(): string
    {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Failed to create temporary file for template');
        }

        try {
            // Write header
            fputcsv($handle, [
                'email',
                'first_name',
                'last_name',
                'phone_number',
                'segment',
                'status',
            ]);

            // Write example rows
            fputcsv($handle, [
                'john.doe@example.com',
                'John',
                'Doe',
                '+40723456789',
                'regular',
                'active',
            ]);

            fputcsv($handle, [
                'jane.smith@example.com',
                'Jane',
                'Smith',
                '+40723456790',
                'vip',
                'active',
            ]);

            rewind($handle);
            $csv = stream_get_contents($handle);

            if (false === $csv) {
                throw new \RuntimeException('Failed to read template content');
            }

            return $csv;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Get filtered customers from repository.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<Customer>
     */
    private function getFilteredCustomers(TenantId $tenantId, array $filters): array
    {
        // If segment filter is provided, use findBySegment
        if (isset($filters['segment']) && is_string($filters['segment'])) {
            $customers = $this->customerRepository->findBySegment($filters['segment'], $tenantId);
        } else {
            $customers = $this->customerRepository->findAll($tenantId);
        }

        // Apply additional filters in memory
        // TODO: Move these filters to repository for better performance
        if (!empty($filters)) {
            $customers = array_filter($customers, function (Customer $customer) use ($filters) {
                // Filter by status (isActive)
                if (isset($filters['status'])) {
                    $isActive = 'active' === $filters['status'];
                    if ($customer->isActive() !== $isActive) {
                        return false;
                    }
                }

                // Filter by date range
                if (isset($filters['date_from'])) {
                    $dateFrom = $filters['date_from'];
                    if ($dateFrom instanceof \DateTimeImmutable && $customer->createdAt() < $dateFrom) {
                        return false;
                    }
                }

                if (isset($filters['date_to'])) {
                    $dateTo = $filters['date_to'];
                    if ($dateTo instanceof \DateTimeImmutable && $customer->createdAt() > $dateTo) {
                        return false;
                    }
                }

                return true;
            });
        }

        return array_values($customers);
    }

    /**
     * Convert customer to CSV row.
     *
     * @return array<string>
     */
    private function customerToCsvRow(Customer $customer): array
    {
        return [
            $customer->id()->toString(),
            $customer->email()->toString(),
            $customer->firstName(),
            $customer->lastName(),
            $customer->phoneNumber() ?? '',
            $customer->segment()->toString(),
            (string) $customer->loyaltyPoints(),
            $customer->isActive() ? 'active' : 'inactive',
            $customer->createdAt()->format('Y-m-d H:i:s'),
            $customer->updatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convert customer to array for JSON export.
     *
     * @return array<string, mixed>
     */
    private function customerToArray(Customer $customer): array
    {
        return [
            'id' => $customer->id()->toString(),
            'email' => $customer->email()->toString(),
            'first_name' => $customer->firstName(),
            'last_name' => $customer->lastName(),
            'full_name' => $customer->fullName(),
            'phone_number' => $customer->phoneNumber(),
            'segment' => $customer->segment()->toString(),
            'loyalty_points' => $customer->loyaltyPoints(),
            'status' => $customer->isActive() ? 'active' : 'inactive',
            'created_at' => $customer->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $customer->updatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Batch customers for memory-efficient processing.
     *
     * @param array<Customer> $customers
     *
     * @return \Generator<array<Customer>>
     */
    private function batchCustomers(array $customers, int $batchSize): \Generator
    {
        $batch = [];
        foreach ($customers as $customer) {
            $batch[] = $customer;

            if (count($batch) >= $batchSize) {
                yield $batch;
                $batch = [];
            }
        }

        if (!empty($batch)) {
            yield $batch;
        }
    }
}
