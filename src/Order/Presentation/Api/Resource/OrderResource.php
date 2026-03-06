<?php

declare(strict_types=1);

namespace App\Order\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Order\Presentation\Api\Processor\CancelOrderProcessor;
use App\Order\Presentation\Api\Processor\PlaceOrderProcessor;
use App\Order\Presentation\Api\Processor\UpdateOrderStatusProcessor;
use App\Order\Presentation\Api\Provider\OrderCollectionProvider;
use App\Order\Presentation\Api\Provider\OrderItemProvider;

#[ApiResource(
    shortName: 'Order',
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            provider: OrderCollectionProvider::class
        ),
        new Get(
            security: "is_granted('ROLE_USER') or (object != null and object.guestToken != null and object.guestToken == request.query.get('token'))",
            provider: OrderItemProvider::class
        ),
        new Post(
            processor: PlaceOrderProcessor::class
        ),
        new Patch(
            uriTemplate: '/orders/{id}/status',
            security: "is_granted('ROLE_ADMIN')",
            provider: OrderItemProvider::class,
            processor: UpdateOrderStatusProcessor::class
        ),
        new Patch(
            uriTemplate: '/orders/{id}/cancel',
            security: "is_granted('ROLE_USER')",
            provider: OrderItemProvider::class,
            processor: CancelOrderProcessor::class
        ),
    ]
)]
class OrderResource
{
    public ?string $id = null;
    public ?string $tenantId = null;
    public ?string $customerEmail = null;
    public ?string $status = null;
    /** @var array<string, mixed> */
    public array $lines = [];
    public ?array $shippingAddress = null;
    public ?array $billingAddress = null;
    public ?int $totalAmount = null;
    public ?string $totalCurrency = null;
    public array $appliedPromotions = [];
    public ?int $discountAmount = null;
    public ?string $discountCurrency = null;
    public ?string $couponCode = null;
    public array $promotionContext = [];
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    public ?string $guestToken = null;

    /**
     * HATEOAS navigation links for related resources.
     *
     * @return array<string, array{href: string}>|null
     */
    public function getLinks(): ?array
    {
        if (null === $this->id) {
            return null;
        }

        $links = [
            'self' => ['href' => '/api/v1/orders/'.$this->id],
            'cancel' => ['href' => '/api/v1/orders/'.$this->id.'/cancel'],
            'fulfillments' => ['href' => '/api/v1/fulfillments?orderId='.$this->id],
        ];

        if (null !== $this->customerEmail) {
            $links['customer_orders'] = ['href' => '/api/v1/orders?customerEmail='.urlencode($this->customerEmail)];
        }

        return $links;
    }
}
