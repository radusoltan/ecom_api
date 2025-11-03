<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Internationalization\Application\Query\GetMissingTranslations;
use App\Shared\Infrastructure\ApiPlatform\State\TenantContextProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Missing Translations Provider
 *
 * Handles GET /api/translations/missing
 *
 * Returns list of translation keys missing in target locale
 */
final readonly class MissingTranslationsProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextProvider $tenantContext,
        private RequestStack $requestStack,
    ) {}

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     * @return array<array{key: string, domain: string}>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new BadRequestHttpException('No request found');
        }

        // Get required targetLocale parameter
        $targetLocale = $request->query->get('targetLocale');
        if ($targetLocale === null) {
            throw new BadRequestHttpException('Missing required parameter "targetLocale"');
        }

        // Get optional parameters
        $sourceLocale = $request->query->get('sourceLocale');
        $domain = $request->query->get('domain');

        // Create query
        $query = new GetMissingTranslations(
            tenantId: $this->tenantContext->getTenantId(),
            targetLocale: $targetLocale,
            sourceLocale: $sourceLocale,
            domain: $domain,
        );

        // Dispatch query and get result
        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if ($handledStamp === null) {
            throw new \RuntimeException('Query was not handled');
        }

        /** @var array<array{key: string, domain: string}> $result */
        $result = $handledStamp->getResult();

        return $result;
    }
}
