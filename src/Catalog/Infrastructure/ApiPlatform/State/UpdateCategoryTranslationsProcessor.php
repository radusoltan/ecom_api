<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\Command\UpdateCategoryTranslations;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\ValueObject\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * API Platform processor for updating category translations
 * Endpoint: PATCH /api/categories/{id}/translations.
 */
final class UpdateCategoryTranslationsProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $request = $this->getCurrentRequest();

        // Get category ID from URI
        $categoryId = $uriVariables['id'] ?? null;
        if (!$categoryId) {
            throw new BadRequestHttpException('Category ID is required');
        }

        // Get tenant ID from headers
        $tenantId = $request->headers->get('X-Tenant-ID');
        if (!$tenantId) {
            throw new BadRequestHttpException('X-Tenant-ID header is required');
        }

        // Parse request body
        $requestData = json_decode($request->getContent(), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new BadRequestHttpException('Invalid JSON payload');
        }

        // Extract locale and translations
        $locale = $requestData['locale'] ?? null;
        if (!$locale) {
            throw new BadRequestHttpException('Locale is required');
        }

        try {
            $localeVO = Locale::fromString($locale);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException('Invalid locale format: '.$e->getMessage());
        }

        // Create command
        $command = new UpdateCategoryTranslations(
            categoryId: CategoryId::fromString($categoryId),
            tenantId: TenantId::fromString($tenantId),
            locale: $localeVO,
            name: $requestData['name'] ?? null,
            description: $requestData['description'] ?? null
        );

        // Dispatch command
        $this->messageBus->dispatch($command);

        // Return success response
        return [
            'message' => 'Translations updated successfully',
            'categoryId' => $categoryId,
            'locale' => $locale,
        ];
    }

    private function getCurrentRequest(): Request
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            throw new BadRequestHttpException('No active request available.');
        }

        return $request;
    }
}
