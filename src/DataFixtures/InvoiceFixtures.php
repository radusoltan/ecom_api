<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use App\Invoice\Application\Command\CancelInvoice\CancelInvoiceCommand;
use App\Invoice\Application\Command\GenerateInvoice\GenerateInvoiceCommand;
use App\Invoice\Application\Command\GenerateInvoice\GenerateInvoiceLineDto;
use App\Invoice\Application\Command\IssueInvoice\IssueInvoiceCommand;
use App\Invoice\Application\Command\MarkInvoicePaid\MarkInvoicePaidCommand;
use App\Invoice\Domain\Model\InvoiceId;
use App\Order\Infrastructure\Persistence\Doctrine\Entity\OrderEntity;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

final class InvoiceFixtures extends AbstractTenantAwareFixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function __construct(
        EntityManagerInterface $entityManager,
        TenantContext $tenantContext,
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct($entityManager, $tenantContext);
    }

    public static function getGroups(): array
    {
        return ['sprint3', 'sprint3-invoices'];
    }

    public function load(ObjectManager $manager): void
    {
        echo "🧾 Creating invoices from seeded orders...\n";

        $tenantIds = $this->tenantIdsByOwnerEmail();

        foreach (Sprint3SeedData::tenants() as $tenant) {
            $tenantId = TenantId::fromString($tenantIds[$tenant['ownerEmail']]);
            $this->activateTenantContext($tenantId);

            /** @var list<CustomerEntity> $customers */
            $customers = array_values($this->entityManager->getRepository(CustomerEntity::class)->findBy(
                ['tenantId' => $tenantId->toString()]
            ));
            $customerByEmail = [];
            foreach ($customers as $customer) {
                $customerByEmail[$customer->getEmail()] = $customer;
            }

            /** @var list<OrderEntity> $orders */
            $orders = array_values($this->entityManager->getRepository(OrderEntity::class)->findBy(
                ['tenantId' => $tenantId->toString()],
                ['createdAt' => 'ASC']
            ));

            $eligibleOrders = array_values(array_filter(
                $orders,
                static fn (OrderEntity $order): bool => in_array($order->getStatus(), ['processing', 'shipped', 'delivered'], true)
            ));

            $createdInvoices = 0;
            foreach (array_slice($eligibleOrders, 0, Sprint3SeedData::INVOICES_PER_TENANT) as $index => $order) {
                $customer = $customerByEmail[$order->getCustomerEmail()] ?? null;
                if (!$customer instanceof CustomerEntity) {
                    continue;
                }

                $invoiceId = InvoiceId::generate()->toString();
                $billingAddress = $order->getBillingAddress();
                $taxRate = $order->getTaxRate() ?? 0.0;

                $this->commandBus->dispatch(new GenerateInvoiceCommand(
                    invoiceId: $invoiceId,
                    tenantId: $tenantId->toString(),
                    orderId: $order->getId(),
                    customerId: $customer->getId(),
                    billingAddress: [
                        'name' => $customer->getFullName(),
                        'addressLine1' => $billingAddress['street'],
                        'addressLine2' => null,
                        'city' => $billingAddress['city'],
                        'postalCode' => $billingAddress['postalCode'],
                        'country' => $billingAddress['country'],
                        'vatNumber' => null,
                    ],
                    lines: array_map(
                        static fn (array $line): GenerateInvoiceLineDto => new GenerateInvoiceLineDto(
                            description: $line['productName'],
                            quantity: $line['quantity'],
                            unitPriceAmount: $line['unitPriceAmount'],
                            unitPriceCurrency: $line['unitPriceCurrency'],
                            taxRate: $taxRate,
                            productId: $line['productId'],
                            sku: null
                        ),
                        $order->getLines()
                    ),
                    isReverseCharge: $order->isReverseCharge(),
                    notes: sprintf('Seeded invoice for %s order %s', $tenant['name'], $order->getId())
                ));

                $this->commandBus->dispatch(new IssueInvoiceCommand(
                    invoiceId: $invoiceId,
                    dueDate: (new \DateTimeImmutable('+30 days'))->format(\DateTimeInterface::ATOM)
                ));

                if ($index < 20) {
                    $this->commandBus->dispatch(new MarkInvoicePaidCommand(
                        invoiceId: $invoiceId,
                        paidDate: (new \DateTimeImmutable('+1 day'))->format(\DateTimeInterface::ATOM)
                    ));
                } elseif ($index >= 30) {
                    $this->commandBus->dispatch(new CancelInvoiceCommand($invoiceId));
                }

                ++$createdInvoices;
            }

            echo sprintf("   ✓ %s invoices: %d\n", $tenant['name'], $createdInvoices);
            $this->entityManager->clear();
        }

        $this->clearTenantContext();

        echo "✅ Invoices created and linked to orders\n";
    }

    public function getDependencies(): array
    {
        return [
            OrderFixtures::class,
            CustomerFixtures::class,
        ];
    }
}
