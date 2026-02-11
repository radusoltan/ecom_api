<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Invoice\Application\Query\DownloadInvoicePdf\DownloadInvoicePdfQuery;
use App\Invoice\Application\Query\DownloadInvoicePdf\InvoicePdfResult;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Provider for downloading invoice PDF (GET /api/invoices/{id}/pdf).
 *
 * Generates or retrieves the PDF content for an invoice and returns it
 * as a streamable HTTP response with proper headers.
 *
 * Business Flow:
 * 1. Validate invoice ID from URI
 * 2. Extract locale from request (defaults to 'en')
 * 3. Dispatch DownloadInvoicePdfQuery
 * 4. Create StreamedResponse with PDF content
 * 5. Set appropriate headers (Content-Type, Content-Disposition)
 *
 * Error Scenarios:
 * - Missing invoice ID → BadRequestHttpException
 * - Invoice not found → NotFoundException (from handler)
 * - PDF generation fails → RuntimeException (from handler)
 *
 * Response Headers:
 * - Content-Type: application/pdf
 * - Content-Disposition: inline; filename="INV-2025-000001.pdf"
 * - Cache-Control: private, max-age=3600
 */
final readonly class InvoicePdfProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
    ) {
    }

    /**
     * @return StreamedResponse
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StreamedResponse
    {
        $id = $uriVariables['id'] ?? null;

        if (null === $id) {
            throw new BadRequestHttpException('Invoice ID is required');
        }

        // Extract locale from request (Accept-Language header or query parameter)
        $locale = $context['filters']['locale'] ?? 'en';

        // Create query
        $query = new DownloadInvoicePdfQuery(
            invoiceId: $id,
            locale: $locale,
        );

        // Dispatch query and get result
        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('Query was not handled');
        }

        $result = $handledStamp->getResult();

        if (!$result instanceof InvoicePdfResult) {
            throw new \RuntimeException('Invalid query result');
        }

        // Create streamed response
        $response = new StreamedResponse(
            function () use ($result) {
                echo $result->content;
            }
        );

        // Set headers
        $response->headers->set('Content-Type', $result->mimeType);
        $response->headers->set('Content-Disposition', sprintf('inline; filename="%s"', $result->fileName));
        $response->headers->set('Cache-Control', 'private, max-age=3600');

        return $response;
    }
}
