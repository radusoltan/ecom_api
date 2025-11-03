<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Internationalization\Application\Query\ExportTranslations;
use App\Shared\Infrastructure\ApiPlatform\State\TenantContextProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Export Translations Provider
 *
 * Handles GET /api/translations/export
 *
 * Returns file download response with:
 * - Content-Type: text/csv or application/json
 * - Content-Disposition: attachment; filename="translations-{date}.{ext}"
 */
final readonly class ExportTranslationsProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextProvider $tenantContext,
        private RequestStack $requestStack,
    ) {}

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new BadRequestHttpException('No request found');
        }

        // Get format from query parameter
        $format = $request->query->get('format');
        if ($format === null) {
            throw new BadRequestHttpException('Missing "format" query parameter. Allowed values: csv, json');
        }

        if (!in_array($format, ['csv', 'json'], true)) {
            throw new BadRequestHttpException(sprintf(
                'Invalid format "%s". Allowed values: csv, json',
                $format
            ));
        }

        // Build filters from query parameters
        $filters = [];

        if ($request->query->has('domain')) {
            $filters['domain'] = $request->query->get('domain');
        }

        if ($request->query->has('locale')) {
            $filters['locale'] = $request->query->get('locale');
        }

        if ($request->query->has('createdAfter')) {
            $filters['createdAfter'] = $request->query->get('createdAfter');
        }

        if ($request->query->has('createdBefore')) {
            $filters['createdBefore'] = $request->query->get('createdBefore');
        }

        if ($request->query->has('updatedAfter')) {
            $filters['updatedAfter'] = $request->query->get('updatedAfter');
        }

        // Create query
        $query = new ExportTranslations(
            tenantId: $this->tenantContext->getTenantId(),
            format: $format,
            filters: $filters,
        );

        // Dispatch query and get result
        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if ($handledStamp === null) {
            throw new \RuntimeException('Query was not handled');
        }

        /** @var string $content */
        $content = $handledStamp->getResult();

        // Determine content type and filename
        $contentType = match ($format) {
            'csv' => 'text/csv; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            default => throw new \InvalidArgumentException('Invalid format'),
        };

        $filename = sprintf(
            'translations-%s.%s',
            date('Y-m-d-His'),
            $format
        );

        // Return file download response
        return new Response(
            content: $content,
            status: 200,
            headers: [
                'Content-Type' => $contentType,
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }
}
