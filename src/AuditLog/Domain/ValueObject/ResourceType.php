<?php

declare(strict_types=1);

namespace App\AuditLog\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Represents the type of resource being audited
 */
final class ResourceType
{
    private const USER = 'user';
    private const PRODUCT = 'product';
    private const CATEGORY = 'category';
    private const ORDER = 'order';
    private const CUSTOMER = 'customer';
    private const PAYMENT = 'payment';
    private const TENANT = 'tenant';
    private const WAREHOUSE = 'warehouse';
    private const INVENTORY = 'inventory';
    private const PRICE_LIST = 'price_list';
    private const PROMOTION = 'promotion';
    private const CART = 'cart';
    private const REVIEW = 'review';
    private const MEDIA = 'media';
    private const TAX = 'tax';
    private const RETURN = 'return';
    private const SETTINGS = 'settings';

    private const VALID_RESOURCES = [
        self::USER,
        self::PRODUCT,
        self::CATEGORY,
        self::ORDER,
        self::CUSTOMER,
        self::PAYMENT,
        self::TENANT,
        self::WAREHOUSE,
        self::INVENTORY,
        self::PRICE_LIST,
        self::PROMOTION,
        self::CART,
        self::REVIEW,
        self::MEDIA,
        self::TAX,
        self::RETURN,
        self::SETTINGS,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (!in_array($value, self::VALID_RESOURCES, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid resource type: %s. Valid resources are: %s', $value, implode(', ', self::VALID_RESOURCES))
            );
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function user(): self
    {
        return new self(self::USER);
    }

    public static function product(): self
    {
        return new self(self::PRODUCT);
    }

    public static function category(): self
    {
        return new self(self::CATEGORY);
    }

    public static function order(): self
    {
        return new self(self::ORDER);
    }

    public static function customer(): self
    {
        return new self(self::CUSTOMER);
    }

    public static function payment(): self
    {
        return new self(self::PAYMENT);
    }

    public static function tenant(): self
    {
        return new self(self::TENANT);
    }

    public static function warehouse(): self
    {
        return new self(self::WAREHOUSE);
    }

    public static function inventory(): self
    {
        return new self(self::INVENTORY);
    }

    public static function priceList(): self
    {
        return new self(self::PRICE_LIST);
    }

    public static function promotion(): self
    {
        return new self(self::PROMOTION);
    }

    public static function cart(): self
    {
        return new self(self::CART);
    }

    public static function review(): self
    {
        return new self(self::REVIEW);
    }

    public static function media(): self
    {
        return new self(self::MEDIA);
    }

    public static function tax(): self
    {
        return new self(self::TAX);
    }

    public static function return(): self
    {
        return new self(self::RETURN);
    }

    public static function settings(): self
    {
        return new self(self::SETTINGS);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(ResourceType $other): bool
    {
        return $this->value === $other->value;
    }
}
