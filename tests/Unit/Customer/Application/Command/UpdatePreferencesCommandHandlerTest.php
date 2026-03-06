<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\UpdatePreferences\UpdatePreferencesCommand;
use App\Customer\Application\Command\UpdatePreferences\UpdatePreferencesCommandHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdatePreferencesCommandHandler::class)]
final class UpdatePreferencesCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $repository;
    private UpdatePreferencesCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new UpdatePreferencesCommandHandler($this->repository);
    }

    #[Test]
    public function itUpdatesCustomerPreferences(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('user@example.com'),
            'Alice',
            'Smith'
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::isInstanceOf(CustomerId::class),
                self::isInstanceOf(TenantId::class)
            )
            ->willReturn($customer);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Customer::class));

        $command = new UpdatePreferencesCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            preferences: [
                'preferredLanguage' => 'fr',
                'preferredCurrency' => 'EUR',
                'newsletterSubscribed' => true,
                'marketingEmailsAllowed' => false,
                'smsNotificationsAllowed' => false,
                'orderNotificationsEnabled' => true,
                'promotionNotificationsEnabled' => false,
            ]
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenCustomerNotFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $command = new UpdatePreferencesCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            preferences: ['preferredLanguage' => 'en']
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer with ID');

        ($this->handler)($command);
    }
}
