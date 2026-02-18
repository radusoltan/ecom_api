<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Controller;

use App\Pricing\Application\DTO\ExportFilter;
use App\Pricing\Application\Query\ExportPromotions\ExportPromotionsQuery;
use App\Pricing\Application\Service\PricingExportService;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for exporting Promotions to CSV/JSON.
 *
 * Endpoint: GET /api/v1/promotions/export
 */
#[Route('/api/v1/promotions', name: 'api_promotions_')]
final class ExportPromotionsController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly PricingExportService $exportService,
    ) {
    }

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

            // Build filter
            $filter = new ExportFilter(
                tenantId: $tenantId,
                dateFrom: $this->parseDate($request->query->get('date_from')),
                dateTo: $this->parseDate($request->query->get('date_to')),
                isActive: $this->parseBoolean($request->query->get('is_active'))
            );

            // Get format
            $format = $request->query->get('format', 'csv');

            // Create query
            $query = new ExportPromotionsQuery($filter, $format);

            // Dispatch query
            $envelope = $this->messageBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);

            if (!$handledStamp instanceof HandledStamp) {
                throw new \RuntimeException('Query was not handled');
            }

            /** @var string $content */
            $content = $handledStamp->getResult();

            // Determine content type and filename
            if ($query->isCsvFormat()) {
                $contentType = 'text/csv';
                $filename = 'promotions-export-'.date('Y-m-d-His').'.csv';
            } else {
                $contentType = 'application/json';
                $filename = 'promotions-export-'.date('Y-m-d-His').'.json';
            }

            // Return file download response
            $response = new Response($content);
            $response->headers->set('Content-Type', $contentType);
            $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

            return $response;
        } catch (\Exception $e) {
            return new Response(
                'Export failed: '.$e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/import/template', name: 'import_template', methods: ['GET'])]
    public function template(): Response
    {
        try {
            $content = $this->exportService->getPromotionCsvTemplate();

            $response = new Response($content);
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="promotions-import-template.csv"');
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

    private function parseBoolean(?string $value): ?bool
    {
        if (null === $value) {
            return null;
        }

        return in_array(strtolower($value), ['true', '1', 'yes'], true);
    }
}
