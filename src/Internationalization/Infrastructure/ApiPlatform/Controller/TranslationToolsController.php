<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\ApiPlatform\Controller;

use App\Internationalization\Application\Command\ImportTranslations;
use App\Internationalization\Application\Query\ExportTranslations;
use App\Internationalization\Application\Query\GetMissingTranslations;
use App\Internationalization\Application\Query\GetTranslationStats;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for translation management tools: stats, import, export, missing.
 *
 * These endpoints return non-standard shapes (raw arrays, file downloads)
 * so they use Symfony controllers instead of API Platform resources.
 */
#[Route('/api/v1')]
class TranslationToolsController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly MessageBusInterface $commandBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/translation-management/stats', name: 'api_translation_stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (null === $tenantId) {
            return new JsonResponse(['error' => 'X-Tenant-ID header is required'], Response::HTTP_BAD_REQUEST);
        }

        $query = new GetTranslationStats(tenantId: TenantId::fromString($tenantId));
        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return new JsonResponse(['error' => 'Query was not handled'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        /** @var array<string, mixed> $result */
        $result = $handledStamp->getResult();

        return new JsonResponse($result);
    }

    #[Route('/translations/import', name: 'api_translation_import', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (null === $tenantId) {
            return new JsonResponse(['error' => 'X-Tenant-ID header is required'], Response::HTTP_BAD_REQUEST);
        }

        $file = $request->files->get('file');
        if (null === $file) {
            return new JsonResponse(['error' => 'No file uploaded. Use form field "file".'], Response::HTTP_BAD_REQUEST);
        }

        $format = $request->request->get('format');
        if (null === $format || !in_array($format, ['csv', 'json'], true)) {
            return new JsonResponse(['error' => 'Missing or invalid "format" parameter. Allowed values: csv, json'], Response::HTTP_BAD_REQUEST);
        }

        $dryRun = filter_var(
            $request->request->get('dryRun', 'false'),
            FILTER_VALIDATE_BOOLEAN
        );

        try {
            $command = new ImportTranslations(
                tenantId: TenantId::fromString($tenantId),
                file: $file,
                format: $format,
                dryRun: $dryRun,
            );

            $envelope = $this->commandBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);

            if (!$handledStamp instanceof HandledStamp) {
                return new JsonResponse(['error' => 'Command was not handled'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            /** @var \App\Internationalization\Domain\Service\ImportResult $result */
            $result = $handledStamp->getResult();

            return new JsonResponse($result->toArray());
        } catch (\Exception $e) {
            $this->logger->error('Translation import failed', ['error' => $e->getMessage()]);

            return new JsonResponse(['error' => 'Import failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/translations/export', name: 'api_translation_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (null === $tenantId) {
            return new JsonResponse(['error' => 'X-Tenant-ID header is required'], Response::HTTP_BAD_REQUEST);
        }

        $format = $request->query->get('format');
        if (null === $format || !in_array($format, ['csv', 'json'], true)) {
            return new JsonResponse(['error' => 'Missing or invalid "format" parameter. Allowed values: csv, json'], Response::HTTP_BAD_REQUEST);
        }

        $filters = [];
        if ($request->query->has('domain')) {
            $filters['domain'] = $request->query->get('domain');
        }
        if ($request->query->has('locale')) {
            $filters['locale'] = $request->query->get('locale');
        }

        $query = new ExportTranslations(
            tenantId: TenantId::fromString($tenantId),
            format: $format,
            filters: $filters,
        );

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return new JsonResponse(['error' => 'Query was not handled'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        /** @var string $content */
        $content = $handledStamp->getResult();

        $contentType = match ($format) {
            'csv' => 'text/csv; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
        };

        $filename = sprintf('translations-%s.%s', date('Y-m-d-His'), $format);

        return new Response(
            content: $content,
            status: 200,
            headers: [
                'Content-Type' => $contentType,
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]
        );
    }

    #[Route('/translation-management/missing', name: 'api_translation_missing', methods: ['GET'])]
    public function missing(Request $request): JsonResponse
    {
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (null === $tenantId) {
            return new JsonResponse(['error' => 'X-Tenant-ID header is required'], Response::HTTP_BAD_REQUEST);
        }

        $targetLocale = $request->query->get('targetLocale');
        if (null === $targetLocale) {
            return new JsonResponse(['error' => 'Missing required parameter "targetLocale"'], Response::HTTP_BAD_REQUEST);
        }

        $sourceLocale = $request->query->get('sourceLocale');
        $domain = $request->query->get('domain');

        $query = new GetMissingTranslations(
            tenantId: TenantId::fromString($tenantId),
            targetLocale: $targetLocale,
            sourceLocale: $sourceLocale,
            domain: $domain,
        );

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return new JsonResponse(['error' => 'Query was not handled'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        /** @var array<array{key: string, domain: string}> $result */
        $result = $handledStamp->getResult();

        return new JsonResponse($result);
    }
}
