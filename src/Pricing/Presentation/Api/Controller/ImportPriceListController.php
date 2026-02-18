<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Controller;

use App\Pricing\Application\Command\ImportPriceList\ImportPriceListCommand;
use App\Pricing\Application\DTO\ImportResult;
use App\Pricing\Application\Service\PricingImportService;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for importing PriceLists from CSV/JSON files.
 *
 * Endpoint: POST /api/v1/price-lists/import
 */
#[Route('/api/v1/price-lists', name: 'api_price_lists_')]
final class ImportPriceListController extends AbstractController
{
    public function __construct(
        private readonly PricingImportService $importService,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/import', name: 'import', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        try {
            // Get tenant ID from header
            $tenantIdString = $request->headers->get('X-Tenant-ID');
            if (!$tenantIdString) {
                return new JsonResponse(
                    ['error' => 'X-Tenant-ID header is required'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $tenantId = TenantId::fromString($tenantIdString);

            // Get uploaded file
            $file = $request->files->get('file');
            if (!$file) {
                return new JsonResponse(
                    ['error' => 'File upload is required'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Parse and validate file
            $rows = $this->importService->parseFile($file);

            if (empty($rows)) {
                return new JsonResponse(
                    ['error' => 'Import file is empty'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Validate rows
            $validationErrors = $this->importService->validatePriceListRows($rows);
            if (!empty($validationErrors)) {
                return new JsonResponse(
                    [
                        'error' => 'Validation failed',
                        'validation_errors' => $validationErrors,
                    ],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            // Convert rows to array format for command
            $rowsData = array_map(fn ($row) => $row->data, $rows);

            // Check if should process async
            $updateExisting = $request->query->getBoolean('update_existing', true);

            if ($this->importService->shouldProcessAsync(count($rows))) {
                // TODO: Dispatch async message for large imports
                return new JsonResponse(
                    [
                        'message' => 'Import queued for asynchronous processing',
                        'total_rows' => count($rows),
                    ],
                    Response::HTTP_ACCEPTED
                );
            }

            // Process synchronously
            $command = new ImportPriceListCommand(
                tenantId: $tenantId,
                rows: $rowsData,
                updateExisting: $updateExisting
            );

            $envelope = $this->messageBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);

            if (!$handledStamp instanceof HandledStamp) {
                throw new \RuntimeException('Command was not handled');
            }

            /** @var ImportResult $result */
            $result = $handledStamp->getResult();

            return new JsonResponse(
                $result->toArray(),
                $result->isSuccessful() ? Response::HTTP_OK : Response::HTTP_PARTIAL_CONTENT
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Internal server error: '.$e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
