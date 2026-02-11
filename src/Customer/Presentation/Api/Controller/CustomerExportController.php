<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Controller;

use App\Customer\Application\DTO\ExportResult;
use App\Customer\Application\Query\ExportCustomers\ExportCustomersQuery;
use App\Customer\Application\Service\CustomerExportService;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for exporting customers to CSV/JSON.
 *
 * Endpoints:
 * - GET /api/customers/export?format=csv&segment=vip&status=active
 * - GET /api/customers/export/template (CSV import template)
 *
 * Security: Requires ROLE_ADMIN or ROLE_MANAGER
 */
#[Route('/api/customers', name: 'api_customers_')]
#[IsGranted('ROLE_MANAGER')]
final class CustomerExportController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly CustomerExportService $exportService
    ) {
    }

    /**
     * Export customers to CSV or JSON.
     *
     * Query parameters:
     * - format: csv|json (default: csv)
     * - segment: vip|regular|new (optional)
     * - status: active|inactive (optional)
     * - date_from: Y-m-d (optional)
     * - date_to: Y-m-d (optional)
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        try {
            // Get tenant ID from header
            $tenantIdString = $request->headers->get('X-Tenant-ID');
            if (!$tenantIdString) {
                return new Response('X-Tenant-ID header is required', Response::HTTP_BAD_REQUEST);
            }

            $tenantId = TenantId::fromString($tenantIdString);

            // Build filters
            $filters = [];

            if ($segment = $request->query->get('segment')) {
                $filters['segment'] = $segment;
            }

            if ($status = $request->query->get('status')) {
                $filters['status'] = $status;
            }

            $dateFromParam = $request->query->get('date_from');
            if (is_string($dateFromParam)) {
                $filters['date_from'] = $this->parseDate($dateFromParam);
            }

            $dateToParam = $request->query->get('date_to');
            if (is_string($dateToParam)) {
                $filters['date_to'] = $this->parseDate($dateToParam);
            }

            // Get format (default: csv)
            $formatParam = $request->query->get('format', 'csv');
            $format = is_string($formatParam) ? $formatParam : 'csv';

            // Create and dispatch query
            $query = new ExportCustomersQuery(
                tenantId: $tenantId,
                format: $format,
                filters: $filters
            );

            $envelope = $this->messageBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);

            if (!$handledStamp instanceof HandledStamp) {
                throw new \RuntimeException('Query was not handled');
            }

            /** @var ExportResult $result */
            $result = $handledStamp->getResult();

            // Return file download response
            $response = new Response($result->content);
            $response->headers->set('Content-Type', $result->mimeType);
            $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $result->filename));
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            return $response;
        } catch (\InvalidArgumentException $e) {
            return new Response(
                'Invalid request: '.$e->getMessage(),
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            return new Response(
                'Export failed: '.$e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get CSV import template.
     *
     * Returns a CSV template file with example data for customer imports.
     */
    #[Route('/export/template', name: 'export_template', methods: ['GET'])]
    public function template(): Response
    {
        try {
            $content = $this->exportService->getCsvTemplate();

            $response = new Response($content);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="customers-import-template.csv"');
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

            return $response;
        } catch (\Exception $e) {
            return new Response(
                'Template generation failed: '.$e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function parseDate(?string $date): ?\DateTimeImmutable
    {
        if (!$date) {
            return null;
        }

        try {
            return new \DateTimeImmutable($date);
        } catch (\Exception) {
            return null;
        }
    }
}
