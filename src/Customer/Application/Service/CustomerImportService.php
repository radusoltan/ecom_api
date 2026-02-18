<?php

declare(strict_types=1);

namespace App\Customer\Application\Service;

use App\Customer\Application\DTO\ImportResult;
use App\Customer\Application\DTO\ImportRowError;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for importing customer data from CSV/JSON files.
 *
 * Handles validation, duplicate detection, and batch processing with transactions.
 *
 * Business Rules:
 * - Required fields: email, first_name, last_name
 * - Optional fields: phone_number, segment, status
 * - Email must be unique per tenant
 * - Phone must be E.164 format
 * - Segment: vip, regular, new
 * - Status: active, inactive
 * - Batch size: 100 records per transaction
 * - Duplicate handling: skip or update based on updateExisting flag
 */
final readonly class CustomerImportService
{
    private const BATCH_SIZE = 100;
    private const REQUIRED_CSV_FIELDS = ['email', 'first_name', 'last_name'];
    private const VALID_SEGMENTS = ['vip', 'regular', 'new'];
    private const VALID_STATUSES = ['active', 'inactive'];

    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Import customers from CSV content.
     */
    public function importFromCsv(TenantId $tenantId, string $content, bool $updateExisting = false): ImportResult
    {
        $rows = $this->parseCsv($content);

        return $this->processImport($tenantId, $rows, $updateExisting);
    }

    /**
     * Import customers from JSON content.
     */
    public function importFromJson(TenantId $tenantId, string $content, bool $updateExisting = false): ImportResult
    {
        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Invalid JSON format: '.$e->getMessage());
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException('JSON content must be an array of objects');
        }

        return $this->processImport($tenantId, $data, $updateExisting);
    }

    /**
     * Validate a single row and return errors.
     *
     * @param array<string, mixed> $row
     *
     * @return array<ImportRowError>
     */
    public function validateRow(array $row, int $rowNumber): array
    {
        $errors = [];

        // Check required fields
        foreach (self::REQUIRED_CSV_FIELDS as $field) {
            if (!isset($row[$field]) || '' === trim((string) $row[$field])) {
                $errors[] = new ImportRowError(
                    row: $rowNumber,
                    field: $field,
                    message: 'Field is required',
                    value: $row[$field] ?? null
                );
            }
        }

        // Validate email format
        if (isset($row['email'])) {
            try {
                Email::fromString($row['email']);
            } catch (\InvalidArgumentException $e) {
                $errors[] = new ImportRowError(
                    row: $rowNumber,
                    field: 'email',
                    message: 'Invalid email format',
                    value: $row['email']
                );
            }
        }

        // Validate phone format (if provided)
        if (isset($row['phone_number']) && '' !== trim($row['phone_number'])) {
            if (!preg_match('/^\+[1-9]\d{1,14}$/', $row['phone_number'])) {
                $errors[] = new ImportRowError(
                    row: $rowNumber,
                    field: 'phone_number',
                    message: 'Invalid phone format. Must be E.164 format (e.g., +40723456789)',
                    value: $row['phone_number']
                );
            }
        }

        // Validate segment
        if (isset($row['segment']) && '' !== trim($row['segment'])) {
            if (!in_array(strtolower($row['segment']), self::VALID_SEGMENTS, true)) {
                $errors[] = new ImportRowError(
                    row: $rowNumber,
                    field: 'segment',
                    message: 'Invalid segment. Must be one of: '.implode(', ', self::VALID_SEGMENTS),
                    value: $row['segment']
                );
            }
        }

        // Validate status
        if (isset($row['status']) && '' !== trim($row['status'])) {
            if (!in_array(strtolower($row['status']), self::VALID_STATUSES, true)) {
                $errors[] = new ImportRowError(
                    row: $rowNumber,
                    field: 'status',
                    message: 'Invalid status. Must be one of: '.implode(', ', self::VALID_STATUSES),
                    value: $row['status']
                );
            }
        }

        // Validate name lengths
        if (isset($row['first_name'])) {
            $length = mb_strlen(trim($row['first_name']));
            if ($length < 2 || $length > 50) {
                $errors[] = new ImportRowError(
                    row: $rowNumber,
                    field: 'first_name',
                    message: 'First name must be between 2 and 50 characters',
                    value: $row['first_name']
                );
            }
        }

        if (isset($row['last_name'])) {
            $length = mb_strlen(trim($row['last_name']));
            if ($length < 2 || $length > 50) {
                $errors[] = new ImportRowError(
                    row: $rowNumber,
                    field: 'last_name',
                    message: 'Last name must be between 2 and 50 characters',
                    value: $row['last_name']
                );
            }
        }

        return $errors;
    }

    /**
     * Parse CSV content into array of rows.
     *
     * @return array<array<string, string>>
     */
    private function parseCsv(string $content): array
    {
        $lines = str_getcsv($content, "\n");
        // @phpstan-ignore empty.variable (PHPStan doesn't understand str_getcsv can return empty array from empty string)
        if (0 === count($lines)) {
            throw new \InvalidArgumentException('CSV file is empty');
        }

        // Parse header
        $firstLine = array_shift($lines);
        if (null === $firstLine) {
            throw new \InvalidArgumentException('CSV file has no header');
        }

        $headerRaw = str_getcsv($firstLine);

        // Filter out null values and ensure all strings
        $header = array_map(fn ($val) => (string) $val, array_filter($headerRaw, fn ($val) => null !== $val));

        // Validate required columns exist
        $missingColumns = array_diff(self::REQUIRED_CSV_FIELDS, $header);
        if (!empty($missingColumns)) {
            throw new \InvalidArgumentException('CSV is missing required columns: '.implode(', ', $missingColumns));
        }

        // Parse rows
        $rows = [];
        foreach ($lines as $line) {
            if (null === $line || '' === trim($line)) {
                continue; // Skip empty lines
            }

            $valuesRaw = str_getcsv($line);

            // Convert values to strings, filtering nulls
            $values = array_map(fn ($val) => (string) ($val ?? ''), $valuesRaw);

            $row = array_combine($header, $values);
            if ($row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Process import with batch transactions.
     *
     * @param array<array<string, mixed>> $rows
     */
    private function processImport(TenantId $tenantId, array $rows, bool $updateExisting): ImportResult
    {
        $totalRows = count($rows);
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        // Process rows in batches
        $batches = array_chunk($rows, self::BATCH_SIZE, true);

        foreach ($batches as $batch) {
            $this->entityManager->beginTransaction();

            try {
                foreach ($batch as $index => $row) {
                    $rowNumber = is_int($index) ? $index + 2 : 2; // +2 because: 1 for header, 1 for 1-based indexing

                    // Validate row
                    $validationErrors = $this->validateRow($row, $rowNumber);
                    if (!empty($validationErrors)) {
                        foreach ($validationErrors as $error) {
                            $errors[$rowNumber] = $error->toString();
                        }
                        ++$skipped;

                        continue;
                    }

                    // Try to import/update customer
                    try {
                        $email = Email::fromString($row['email']);
                        $existing = $this->customerRepository->findByEmail($email, $tenantId);

                        if ($existing instanceof Customer) {
                            if ($updateExisting) {
                                // Update existing customer
                                $existing->updateProfile(
                                    firstName: trim($row['first_name']),
                                    lastName: trim($row['last_name']),
                                    phoneNumber: !empty($row['phone_number']) ? trim($row['phone_number']) : null
                                );

                                // Update segment if provided
                                if (isset($row['segment']) && '' !== trim($row['segment'])) {
                                    $newSegment = CustomerSegment::fromString(strtolower($row['segment']));
                                    if (!$existing->segment()->equals($newSegment)) {
                                        $existing->changeSegment($newSegment);
                                    }
                                }

                                // Update status if provided
                                if (isset($row['status']) && '' !== trim($row['status'])) {
                                    $shouldBeActive = 'active' === strtolower($row['status']);
                                    if ($shouldBeActive && !$existing->isActive()) {
                                        $existing->activate();
                                    } elseif (!$shouldBeActive && $existing->isActive()) {
                                        $existing->deactivate();
                                    }
                                }

                                $this->customerRepository->save($existing);
                                ++$updated;
                            } else {
                                // Skip duplicate
                                ++$skipped;
                                $errors[$rowNumber] = sprintf('Customer with email "%s" already exists', $email->toString());
                            }
                        } else {
                            // Create new customer
                            $customer = Customer::register(
                                id: CustomerId::generate(),
                                tenantId: $tenantId,
                                email: $email,
                                firstName: trim($row['first_name']),
                                lastName: trim($row['last_name']),
                                phoneNumber: !empty($row['phone_number']) ? trim($row['phone_number']) : null
                            );

                            // Set segment if provided
                            if (isset($row['segment']) && '' !== trim($row['segment'])) {
                                $segment = CustomerSegment::fromString(strtolower($row['segment']));
                                if (!$customer->segment()->equals($segment)) {
                                    $customer->changeSegment($segment);
                                }
                            }

                            // Set status if provided (default is active)
                            if (isset($row['status']) && 'inactive' === strtolower($row['status'])) {
                                $customer->deactivate();
                            }

                            $this->customerRepository->save($customer);
                            ++$imported;
                        }
                    } catch (\Exception $e) {
                        $errors[$rowNumber] = sprintf('Failed to import: %s', $e->getMessage());
                        ++$skipped;
                        $this->logger->error('Customer import error', [
                            'row' => $rowNumber,
                            'email' => $row['email'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $this->entityManager->commit();
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                $this->logger->error('Batch import failed', [
                    'error' => $e->getMessage(),
                ]);

                throw new \RuntimeException('Batch import failed: '.$e->getMessage(), 0, $e);
            }
        }

        return new ImportResult(
            totalRows: $totalRows,
            imported: $imported,
            updated: $updated,
            skipped: $skipped,
            errors: $errors
        );
    }
}
