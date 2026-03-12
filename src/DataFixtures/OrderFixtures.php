<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerAddressEntity;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use App\Order\Application\Command\CancelOrderCommand;
use App\Order\Application\Command\PlaceOrderCommand;
use App\Order\Application\Command\UpdateOrderStatusCommand;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Order fixtures - creates realistic multi-tenant order history.
 */
class OrderFixtures extends AbstractTenantAwareFixture implements DependentFixtureInterface, FixtureGroupInterface
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
        return ['sprint3', 'sprint3-orders'];
    }

    public function load(ObjectManager $manager): void
    {
        echo "📦 Creating order history...\n";

        $tenantIds = $this->tenantIdsByOwnerEmail();

        foreach (Sprint3SeedData::tenants() as $tenant) {
            $tenantId = TenantId::fromString($tenantIds[$tenant['ownerEmail']]);
            $this->activateTenantContext($tenantId);

            /** @var list<CustomerEntity> $customers */
            $customers = array_values($this->entityManager->getRepository(CustomerEntity::class)->findBy(
                ['tenantId' => $tenantId->toString()],
                ['createdAt' => 'ASC']
            ));
            $customers = array_values(array_filter(
                $customers,
                static fn (CustomerEntity $customer): bool => $customer->isActive()
            ));

            /** @var list<ProductEntity> $products */
            $products = array_values($this->entityManager->getRepository(ProductEntity::class)->findBy(
                ['tenantId' => $tenantId->toString()],
                ['createdAt' => 'ASC']
            ));

            $orderCounter = 0;

            foreach (Sprint3SeedData::ORDER_STATUS_DISTRIBUTION as $targetStatus => $count) {
                for ($index = 0; $index < $count; ++$index) {
                    /** @var CustomerEntity $customer */
                    $customer = $customers[($orderCounter + $index) % count($customers)];
                    $shippingAddress = $this->addressPayload($this->pickAddress($customer, true));
                    $billingAddress = $this->addressPayload($this->pickAddress($customer, false));
                    $lines = $this->buildLines($products, $orderCounter + $index);
                    $orderId = OrderId::generate()->toString();

                    $this->commandBus->dispatch(new PlaceOrderCommand(
                        orderId: $orderId,
                        tenantId: $tenantId->toString(),
                        customerEmail: $customer->getEmail(),
                        lines: $lines,
                        shippingAddress: $shippingAddress,
                        billingAddress: $billingAddress,
                    ));

                    $this->transitionOrder($orderId, $tenantId->toString(), $targetStatus, ($orderCounter + $index) % 2 === 0);
                }

                $orderCounter += $count;
            }

            echo sprintf("   ✓ %s orders: %d\n", $tenant['name'], $orderCounter);
            $this->entityManager->clear();
        }

        $this->clearTenantContext();

        echo "✅ Orders created across all target statuses\n";
    }

    /**
     * @param array<int, ProductEntity> $products
     *
     * @return array<int, array{productId: string, productName: string, quantity: int, unitPriceAmount: int, unitPriceCurrency: string}>
     */
    private function buildLines(array $products, int $seed): array
    {
        $lineCount = 1 + ($seed % 3);
        $lines = [];

        for ($offset = 0; $offset < $lineCount; ++$offset) {
            /** @var ProductEntity $product */
            $product = $products[($seed + ($offset * 7)) % count($products)];
            $lines[] = [
                'productId' => $product->getId(),
                'productName' => $product->getName(),
                'quantity' => 1 + (($seed + $offset) % 2),
                'unitPriceAmount' => $product->getPriceAmount(),
                'unitPriceCurrency' => $product->getPriceCurrency(),
            ];
        }

        return $lines;
    }

    private function pickAddress(CustomerEntity $customer, bool $shipping): CustomerAddressEntity
    {
        $addresses = $customer->getAddressEntities()->toArray();

        foreach ($addresses as $address) {
            if (
                $address instanceof CustomerAddressEntity
                && !$address->isDeleted()
                && ($shipping ? $address->isDefaultShipping() : $address->isDefaultBilling())
            ) {
                return $address;
            }
        }

        foreach ($addresses as $address) {
            if ($address instanceof CustomerAddressEntity && !$address->isDeleted()) {
                return $address;
            }
        }

        throw new \RuntimeException(sprintf('Customer %s does not have a usable address for order fixtures', $customer->getId()));
    }

    /**
     * @return array{street: string, city: string, state: string, postalCode: string, country: string}
     */
    private function addressPayload(CustomerAddressEntity $address): array
    {
        return [
            'street' => $address->getStreet(),
            'city' => $address->getCity(),
            'state' => $address->getState() ?? 'NA',
            'postalCode' => $address->getPostalCode(),
            'country' => $address->getCountry(),
        ];
    }

    private function transitionOrder(string $orderId, string $tenantId, string $targetStatus, bool $cancelAfterProcessing): void
    {
        if ('processing' === $targetStatus) {
            $this->commandBus->dispatch(new UpdateOrderStatusCommand($orderId, $tenantId, 'processing'));

            return;
        }

        if ('shipped' === $targetStatus) {
            $this->advanceToShipped($orderId, $tenantId);

            return;
        }

        if ('delivered' === $targetStatus) {
            $this->advanceToDelivered($orderId, $tenantId);

            return;
        }

        if ('cancelled' === $targetStatus) {
            if ($cancelAfterProcessing) {
                $this->cancelFromProcessing($orderId, $tenantId);

                return;
            }

            $this->commandBus->dispatch(new CancelOrderCommand($orderId, $tenantId));
        }
    }

    private function advanceToShipped(string $orderId, string $tenantId): void
    {
        $this->commandBus->dispatch(new UpdateOrderStatusCommand($orderId, $tenantId, 'processing'));
        $this->commandBus->dispatch(new UpdateOrderStatusCommand($orderId, $tenantId, 'shipped'));
    }

    private function advanceToDelivered(string $orderId, string $tenantId): void
    {
        $this->advanceToShipped($orderId, $tenantId);
        $this->commandBus->dispatch(new UpdateOrderStatusCommand($orderId, $tenantId, 'delivered'));
    }

    private function cancelFromProcessing(string $orderId, string $tenantId): void
    {
        $this->commandBus->dispatch(new UpdateOrderStatusCommand($orderId, $tenantId, 'processing'));
        $this->commandBus->dispatch(new CancelOrderCommand($orderId, $tenantId));
    }

    public function getDependencies(): array
    {
        return [
            ProductFixtures::class,
            CustomerFixtures::class,
            InventoryFixtures::class,
        ];
    }
}
