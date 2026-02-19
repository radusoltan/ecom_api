<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Application\Command;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Application\Command\GrantConsentCommand;
use App\Privacy\Application\Command\GrantConsentCommandHandler;
use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrantConsentCommandHandler::class)]
final class GrantConsentCommandHandlerTest extends TestCase
{
    private ConsentRepositoryInterface $repository;
    private GrantConsentCommandHandler $handler;
    private string $validConsentText;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ConsentRepositoryInterface::class);
        $this->handler = new GrantConsentCommandHandler($this->repository);
        $this->validConsentText = 'I consent to the processing of my personal data for the purpose of marketing communications as described in the privacy policy.';
    }

    #[Test]
    public function handleCreatesAndSavesNewConsent(): void
    {
        $command = new GrantConsentCommand(
            tenantId: TenantId::generate(),
            customerId: CustomerId::generate(),
            purpose: ConsentPurpose::marketing(),
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0',
            consentText: $this->validConsentText,
            consentVersion: 'v1.0.0'
        );

        $this->repository->expects(self::once())
            ->method('findActiveByCustomerAndPurpose')
            ->with($command->customerId, $command->purpose)
            ->willReturn(null);

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Consent $consent) use ($command): bool {
                return $consent->tenantId()->equals($command->tenantId)
                    && $consent->customerId()->equals($command->customerId)
                    && $consent->purpose()->equals($command->purpose)
                    && $consent->isGranted();
            }));

        $result = ($this->handler)($command);

        self::assertInstanceOf(ConsentId::class, $result);
    }

    #[Test]
    public function handleReturnsExistingConsentIdWhenAlreadyGranted(): void
    {
        $existingConsentId = ConsentId::generate();
        $customerId = CustomerId::generate();
        $purpose = ConsentPurpose::marketing();

        $existingConsent = Consent::grant(
            $existingConsentId,
            TenantId::generate(),
            $customerId,
            $purpose,
            '10.0.0.1',
            'Chrome/120',
            $this->validConsentText,
            'v1.0.0'
        );

        $command = new GrantConsentCommand(
            tenantId: TenantId::generate(),
            customerId: $customerId,
            purpose: $purpose,
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0',
            consentText: $this->validConsentText,
            consentVersion: 'v1.0.0'
        );

        $this->repository->expects(self::once())
            ->method('findActiveByCustomerAndPurpose')
            ->willReturn($existingConsent);

        $this->repository->expects(self::never())
            ->method('save');

        $result = ($this->handler)($command);

        self::assertTrue($result->equals($existingConsentId));
    }
}
