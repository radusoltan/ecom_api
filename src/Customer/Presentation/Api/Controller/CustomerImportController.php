<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Controller;

use App\Customer\Application\Command\ImportCustomers\ImportCustomersCommand;
use App\Customer\Application\DTO\ImportResult;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for importing customers from CSV/JSON files.
 *
 * Endpoint: POST /api/customers/import
 *
 * Security: Requires ROLE_ADMIN or ROLE_MANAGER
 * Rate Limit: Max 10 requests per minute
 */
#[Route('/api/customers', name: 'api_customers_')]
#[IsGranted('ROLE_MANAGER')]
final class CustomerImportController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
    }

    /**
     * Import customers from CSV or JSON file.
     *
     * Request:
     * - Content-Type: multipart/form-data
     * - file: uploaded file (CSV or JSON)
     * - update_existing: true|false (optional, default: false)
     *
     * Response:
     * - JSON with import statistics and errors
     */
    #[Route('/import', name: 'import', methods: ['POST'])]
    public function import(Request $request): Response
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
            if (!$file instanceof UploadedFile) {
                return new JsonResponse(
                    ['error' => 'File upload is required'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Validate file
            if (!$file->isValid()) {
                return new JsonResponse(
                    ['error' => 'Invalid file upload: '.$file->getErrorMessage()],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Determine format from file extension
            $extension = strtolower($file->getClientOriginalExtension());
            $format = match ($extension) {
                'csv' => 'csv',
                'json' => 'json',
                default => null,
            };

            if (null === $format) {
                return new JsonResponse(
                    ['error' => 'Unsupported file format. Only CSV and JSON files are allowed.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Read file content
            $content = file_get_contents($file->getPathname());
            if (false === $content) {
                return new JsonResponse(
                    ['error' => 'Failed to read file content'],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }

            // Get update_existing flag
            $updateExisting = 'true' === $request->request->get('update_existing', 'false');

            // Create and dispatch command
            $command = new ImportCustomersCommand(
                tenantId: $tenantId,
                format: $format,
                content: $content,
                updateExisting: $updateExisting
            );

            $envelope = $this->messageBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);

            if (!$handledStamp instanceof HandledStamp) {
                throw new \RuntimeException('Command was not handled');
            }

            /** @var ImportResult $result */
            $result = $handledStamp->getResult();

            // Return result as JSON
            return new JsonResponse(
                $result->toArray(),
                $result->isSuccessful() ? Response::HTTP_OK : Response::HTTP_PARTIAL_CONTENT
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['error' => 'Invalid request: '.$e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Import failed: '.$e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
