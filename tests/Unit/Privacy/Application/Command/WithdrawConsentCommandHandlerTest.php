<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Application\Command;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Application\Command\WithdrawConsentCommand;
use App\Privacy\Application\Command\WithdrawConsentCommandHandler;
use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WithdrawConsentCommandHandler::class)]
final class WithdrawConsentCommandHandlerTest extends TestCase
{
    private ConsentRepositoryInterface $repository;
    private WithdrawConsentCommandHandler $handler;
    private string $validConsentText;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ConsentRepositoryInterface::class);
        $this->handler = new WithdrawConsentCommandHandler($this->repository);
        $this->validConsentText = 'I consent to the processing of my personal data for the purpose of marketing communications as described in the privacy policy.';
    }

    #[Test]
    public function handleWithdrawsConsentAndSaves(): void
    {
        $consentId = ConsentId::generate();
        $consent = Consent::grant(
            $consentId,
            TenantId::generate(),
            CustomerId::generate(),
            ConsentPurpose::marketing(),
            '192.168.1.1',
            'Mozilla/5.0',
            $this->validConsentText,
            'v1.0.0'
        );

        $command = new WithdrawConsentCommand($consentId);

        $this->repository->expects(self::once())
            ->method('findById')
            ->with($consentId)
            ->willReturn($consent);

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Consent $saved): bool {
                return !$saved->isGranted();
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function handleThrowsExceptionWhenConsentNotFound(): void
    {
        $consentId = ConsentId::generate();
        $command = new WithdrawConsentCommand($consentId);

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Consent not found');

        ($this->handler)($command);
    }
}
