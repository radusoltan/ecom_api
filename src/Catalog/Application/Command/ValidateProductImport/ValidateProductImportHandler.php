<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ValidateProductImport;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for validating product import data
 */
#[AsMessageHandler]
final readonly class ValidateProductImportHandler
{
    public function __invoke(ValidateProductImportCommand $command): array
    {
        $validRows = 0;
        $invalidRows = 0;
        $errors = [];
        $validData = [];

        foreach ($command->products as $index => $productData) {
            $rowNumber = $index + 1;
            $rowErrors = [];

            // Validate required fields
            if (empty($productData['name']) || trim((string)$productData['name']) === '') {
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'field' => 'name',
                    'message' => 'Product name is required',
                    'value' => $productData['name'] ?? null
                ];
            }

            if (!isset($productData['priceAmount'])) {
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'field' => 'priceAmount',
                    'message' => 'Price amount is required',
                    'value' => null
                ];
            } elseif (!is_numeric($productData['priceAmount']) || (float)$productData['priceAmount'] <= 0) {
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'field' => 'priceAmount',
                    'message' => 'Price amount must be a positive number',
                    'value' => $productData['priceAmount']
                ];
            }

            // Validate optional fields
            if (isset($productData['priceAmount']) && is_numeric($productData['priceAmount'])) {
                $priceAmount = (float)$productData['priceAmount'];
                if ($priceAmount > 999999.99) {
                    $rowErrors[] = [
                        'row' => $rowNumber,
                        'field' => 'priceAmount',
                        'message' => 'Price amount is too large (max: 999999.99)',
                        'value' => $productData['priceAmount']
                    ];
                }
            }

            if (isset($productData['priceCurrency'])) {
                $currency = strtoupper(trim((string)$productData['priceCurrency']));
                if (!in_array($currency, ['USD', 'EUR', 'GBP', 'RON'], true)) {
                    $rowErrors[] = [
                        'row' => $rowNumber,
                        'field' => 'priceCurrency',
                        'message' => 'Invalid currency code (supported: USD, EUR, GBP, RON)',
                        'value' => $productData['priceCurrency']
                    ];
                }
            }

            if (isset($productData['stockQuantity']) && (!is_numeric($productData['stockQuantity']) || (int)$productData['stockQuantity'] < 0)) {
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'field' => 'stockQuantity',
                    'message' => 'Stock quantity must be a non-negative number',
                    'value' => $productData['stockQuantity']
                ];
            }

            // Check name length
            if (isset($productData['name']) && mb_strlen(trim((string)$productData['name'])) > 255) {
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'field' => 'name',
                    'message' => 'Product name is too long (max: 255 characters)',
                    'value' => substr((string)$productData['name'], 0, 50) . '...'
                ];
            }

            // If no errors, add to valid data
            if (empty($rowErrors)) {
                $validRows++;
                $validData[] = $productData;
            } else {
                $invalidRows++;
                $errors = array_merge($errors, $rowErrors);
            }
        }

        return [
            'valid' => $invalidRows === 0,
            'validRows' => $validRows,
            'invalidRows' => $invalidRows,
            'errors' => $errors,
            'data' => $validData
        ];
    }
}
