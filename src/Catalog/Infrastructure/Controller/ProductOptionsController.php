<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Controller;

use App\Catalog\Application\Command\DefineOption;
use App\Catalog\Application\Command\DefineOptionValue;
use App\Catalog\Application\Query\GetProductOptions;
use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for product options management.
 */
#[Route('/api/products/{id}/options')]
final class ProductOptionsController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus,
    ) {
    }

    /**
     * Get all options for a product
     * GET /api/products/{id}/options.
     */
    #[Route('', methods: ['GET'])]
    public function getOptions(string $id, Request $request): JsonResponse
    {
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (!$tenantId) {
            return $this->json(['error' => 'X-Tenant-ID header is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $query = new GetProductOptions(
                ProductId::fromString($id),
                TenantId::fromString($tenantId)
            );

            $envelope = $this->queryBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);
            $options = $handledStamp->getResult();

            return $this->json($options);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Define a new option for a product
     * POST /api/products/{id}/options.
     */
    #[Route('', methods: ['POST'])]
    public function defineOption(string $id, Request $request): JsonResponse
    {
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (!$tenantId) {
            return $this->json(['error' => 'X-Tenant-ID header is required'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return $this->json(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields
        if (!isset($data['code']) || !isset($data['nameTranslations'])) {
            return $this->json(['error' => 'code and nameTranslations are required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $command = new DefineOption(
                ProductId::fromString($id),
                TenantId::fromString($tenantId),
                $data['code'],
                $data['nameTranslations'],
                $data['position'] ?? 0,
                $data['values'] ?? []
            );

            $this->commandBus->dispatch($command);

            return $this->json(
                ['message' => 'Option defined successfully'],
                Response::HTTP_CREATED
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Add a value to an existing option
     * POST /api/products/{id}/options/{code}/values.
     */
    #[Route('/{code}/values', methods: ['POST'])]
    public function addOptionValue(string $id, string $code, Request $request): JsonResponse
    {
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (!$tenantId) {
            return $this->json(['error' => 'X-Tenant-ID header is required'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return $this->json(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields
        if (!isset($data['valueCode']) || !isset($data['nameTranslations'])) {
            return $this->json(['error' => 'valueCode and nameTranslations are required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $command = new DefineOptionValue(
                ProductId::fromString($id),
                TenantId::fromString($tenantId),
                $code,
                $data['valueCode'],
                $data['nameTranslations'],
                $data['position'] ?? 0
            );

            $this->commandBus->dispatch($command);

            return $this->json(
                ['message' => 'Option value added successfully'],
                Response::HTTP_CREATED
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
