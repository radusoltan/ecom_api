<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\Controller;

use App\Catalog\Application\Command\BulkImportProducts\BulkImportProductsCommand;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/products/bulk-import', name: 'api_products_bulk_import', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN')]
final readonly class BulkImportController
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (null === $tenantId || '' === $tenantId) {
            return new JsonResponse(['error' => 'X-Tenant-ID header is required'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['products']) || !is_array($data['products'])) {
            return new JsonResponse(['error' => 'Request body must contain a "products" array'], Response::HTTP_BAD_REQUEST);
        }

        if (count($data['products']) > 1000) {
            return new JsonResponse(['error' => 'Maximum 1000 products per import'], Response::HTTP_BAD_REQUEST);
        }

        $command = new BulkImportProductsCommand(
            products: $data['products'],
        );

        $envelope = $this->messageBus->dispatch($command);
        $handledStamp = $envelope->last(HandledStamp::class);
        $result = $handledStamp?->getResult();

        return new JsonResponse($result ?? ['error' => 'Import failed'], Response::HTTP_OK);
    }
}
