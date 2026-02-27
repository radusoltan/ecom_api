<?php

declare(strict_types=1);

namespace App\Tenant\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Tenant\Application\Query\GetFeatureFlagQuery;
use App\Tenant\Domain\Model\FeatureFlag;
use App\Tenant\Presentation\Api\Resource\FeatureFlagResource;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/** @implements ProviderInterface<FeatureFlagResource> */
final readonly class FeatureFlagItemProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?FeatureFlagResource
    {
        $featureName = $uriVariables['id'] ?? null;

        if (null === $featureName) {
            return null;
        }

        $tenantId = $context['tenant_id'] ?? $this->requestStack->getCurrentRequest()?->headers->get('X-Tenant-ID');

        if (null === $tenantId) {
            return null;
        }

        $envelope = $this->queryBus->dispatch(new GetFeatureFlagQuery($tenantId, (string) $featureName));
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return null;
        }

        $flag = $handledStamp->getResult();

        if (!$flag instanceof FeatureFlag) {
            return null;
        }

        $resource = new FeatureFlagResource();
        $resource->id = $flag->id()->toString();
        $resource->tenantId = $flag->tenantId()->toString();
        $resource->featureName = $flag->featureName();
        $resource->enabled = $flag->isEnabled();
        $resource->configuration = $flag->configuration();
        $resource->description = $flag->description();
        $resource->createdAt = $flag->createdAt()->format(\DateTimeInterface::ATOM);
        $resource->updatedAt = $flag->updatedAt()?->format(\DateTimeInterface::ATOM);

        return $resource;
    }
}
