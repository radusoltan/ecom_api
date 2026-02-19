<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\UpdateConsent\UpdateConsentCommand;
use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Presentation\Api\Resource\CustomerConsentResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Update Single Consent Processor.
 *
 * Updates a single GDPR consent type.
 * Captures IP address and User-Agent for audit trail.
 *
 * @deprecated Use Privacy context consent endpoints instead.
 *             Privacy is the GDPR source of truth; PrivacyConsentSyncSubscriber
 *             propagates consent decisions to Customer context automatically.
 *
 * @implements ProcessorInterface<CustomerConsentResource, CustomerConsentResource>
 */
final readonly class UpdateSingleConsentProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TenantContextInterface $tenantContext,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerConsentResource
    {
        if (!$data instanceof CustomerConsentResource) {
            throw new BadRequestHttpException('Expected CustomerConsentResource');
        }

        // Extract IDs from URI
        if (!isset($uriVariables['customerId']) || !isset($uriVariables['type'])) {
            throw new BadRequestHttpException('Customer ID and consent type are required');
        }

        $customerId = CustomerId::fromString($uriVariables['customerId']);
        $consentTypeString = $uriVariables['type'];

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Validate granted field
        if (null === $data->granted) {
            throw new BadRequestHttpException('Granted field is required');
        }

        // Parse consent type
        try {
            $consentType = ConsentType::fromString($consentTypeString);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException(sprintf('Invalid consent type: %s', $consentTypeString));
        }

        // Capture IP address and User-Agent from request
        $request = $this->requestStack->getCurrentRequest();
        $ipAddress = $request?->getClientIp();
        $userAgent = $request?->headers->get('User-Agent');

        // Create command
        $command = new UpdateConsentCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            consentType: $consentType,
            granted: $data->granted,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        $this->commandBus->dispatch($command);

        // Create response resource
        $resource = new CustomerConsentResource();
        $resource->customerId = $customerId->toString();
        $resource->consentType = $consentTypeString;
        $resource->granted = $data->granted;
        $resource->updatedAt = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        return $resource;
    }
}
