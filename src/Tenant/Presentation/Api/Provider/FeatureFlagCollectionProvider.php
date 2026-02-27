<?php

declare(strict_types=1);

namespace App\Tenant\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Tenant\Application\Query\GetFeatureFlagsQuery;
use App\Tenant\Domain\Model\FeatureFlag;
use App\Tenant\Presentation\Api\Resource\FeatureFlagResource;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/** @implements ProviderInterface<FeatureFlagResource> */
final readonly class FeatureFlagCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private RequestStack $requestStack,
    ) {
    }

    /** @return FeatureFlagResource[] */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $tenantId = $context['tenant_id'] ?? $this->requestStack->getCurrentRequest()?->headers->get('X-Tenant-ID');

        if (null === $tenantId) {
            return [];
        }

        $envelope = $this->queryBus->dispatch(new GetFeatureFlagsQuery($tenantId));
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return [];
        }

        $flags = $handledStamp->getResult();

        if (!is_array($flags)) {
            return [];
        }

        return array_map(fn (FeatureFlag $flag) => $this->toResource($flag), $flags);
    }

    private function toResource(FeatureFlag $flag): FeatureFlagResource
    {
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
