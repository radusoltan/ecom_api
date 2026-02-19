<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\UpdateAllConsents\UpdateAllConsentsCommand;
use App\Customer\Application\DTO\CustomerConsentsDTO;
use App\Customer\Application\Query\GetCustomerConsents\GetCustomerConsentsQuery;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Presentation\Api\Resource\CustomerConsentResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Update All Consents Processor.
 *
 * Updates all GDPR consents at once.
 * Captures IP address and User-Agent for audit trail.
 *
 * @deprecated Use Privacy context consent endpoints instead.
 *             Privacy is the GDPR source of truth; PrivacyConsentSyncSubscriber
 *             propagates consent decisions to Customer context automatically.
 *
 * @implements ProcessorInterface<CustomerConsentResource, CustomerConsentResource>
 */
final readonly class UpdateAllConsentsProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerConsentResource
    {
        if (!$data instanceof CustomerConsentResource) {
            throw new BadRequestHttpException('Expected CustomerConsentResource');
        }

        // Extract customerId from URI
        if (!isset($uriVariables['customerId'])) {
            throw new BadRequestHttpException('Customer ID is required');
        }

        $customerId = CustomerId::fromString($uriVariables['customerId']);

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Validate all consent fields are provided
        if (null === $data->marketingEmail || null === $data->marketingSms
            || null === $data->thirdPartySharing || null === $data->analyticsTracking) {
            throw new BadRequestHttpException('All consent fields are required');
        }

        // Capture IP address and User-Agent from request
        $request = $this->requestStack->getCurrentRequest();
        $ipAddress = $request?->getClientIp();
        $userAgent = $request?->headers->get('User-Agent');

        // Create command
        $command = new UpdateAllConsentsCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            marketingEmail: $data->marketingEmail,
            marketingSms: $data->marketingSms,
            thirdPartySharing: $data->thirdPartySharing,
            analyticsTracking: $data->analyticsTracking,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        $this->commandBus->dispatch($command);

        // Retrieve updated consents
        $query = new GetCustomerConsentsQuery(
            customerId: $customerId,
            tenantId: $tenantId
        );

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var CustomerConsentsDTO|null $dto */
        $dto = $handledStamp->getResult();

        if (null === $dto) {
            throw new \RuntimeException('Consents not found after update');
        }

        // Map DTO to resource
        return $this->mapDtoToResource($dto);
    }

    private function mapDtoToResource(CustomerConsentsDTO $dto): CustomerConsentResource
    {
        $resource = new CustomerConsentResource();
        $resource->customerId = $dto->customerId;
        $resource->marketingEmail = $dto->marketingEmail;
        $resource->marketingSms = $dto->marketingSms;
        $resource->thirdPartySharing = $dto->thirdPartySharing;
        $resource->analyticsTracking = $dto->analyticsTracking;
        $resource->lastUpdatedAt = $dto->lastUpdatedAt->format(\DateTimeInterface::ATOM);

        return $resource;
    }
}
