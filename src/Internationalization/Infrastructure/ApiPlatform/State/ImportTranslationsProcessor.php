<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Internationalization\Application\Command\ImportTranslations;
use App\Shared\Infrastructure\ApiPlatform\State\TenantContextProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Import Translations Processor.
 *
 * Handles POST /api/translations/import
 *
 * Processes multipart/form-data with:
 * - file: UploadedFile (CSV or JSON)
 * - format: string ('csv' or 'json')
 * - dryRun: bool (optional, default false)
 */
final readonly class ImportTranslationsProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TenantContextProvider $tenantContext,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            throw new BadRequestHttpException('No request found');
        }

        // Get uploaded file
        $file = $request->files->get('file');
        if (null === $file) {
            throw new BadRequestHttpException('No file uploaded. Use form field "file".');
        }

        // Get format
        $format = $request->request->get('format');
        if (null === $format) {
            throw new BadRequestHttpException('Missing "format" parameter. Allowed values: csv, json');
        }

        if (!in_array($format, ['csv', 'json'], true)) {
            throw new BadRequestHttpException(sprintf('Invalid format "%s". Allowed values: csv, json', $format));
        }

        // Get dryRun flag
        $dryRun = filter_var(
            $request->request->get('dryRun', 'false'),
            FILTER_VALIDATE_BOOLEAN
        );

        // Create command
        $command = new ImportTranslations(
            tenantId: $this->tenantContext->getTenantId(),
            file: $file,
            format: $format,
            dryRun: $dryRun,
        );

        // Dispatch command and get result
        $envelope = $this->commandBus->dispatch($command);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (null === $handledStamp) {
            throw new \RuntimeException('Command was not handled');
        }

        /** @var \App\Internationalization\Domain\Service\ImportResult $result */
        $result = $handledStamp->getResult();

        return $result->toArray();
    }
}
