<?php

declare(strict_types=1);

namespace App\Tenant\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Tenant\Application\Command\CreateFeatureFlagCommand;
use App\Tenant\Presentation\Api\Resource\FeatureFlagResource;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

/** @implements ProcessorInterface<FeatureFlagResource, FeatureFlagResource> */
final readonly class CreateFeatureFlagProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FeatureFlagResource
    {
        if (!$data instanceof FeatureFlagResource) {
            throw new \InvalidArgumentException('Expected FeatureFlagResource');
        }

        $tenantId = $data->tenantId
            ?? $context['tenant_id']
            ?? $this->requestStack->getCurrentRequest()?->headers->get('X-Tenant-ID')
            ?? throw new \InvalidArgumentException('Tenant ID is required');

        $featureName = $data->featureName ?? throw new \InvalidArgumentException('Feature name is required');

        $command = new CreateFeatureFlagCommand(
            tenantId: $tenantId,
            featureName: $featureName,
            enabled: $data->enabled ?? false,
            configuration: $data->configuration,
            description: $data->description,
        );

        $this->commandBus->dispatch($command);

        $data->tenantId = $tenantId;

        return $data;
    }
}
