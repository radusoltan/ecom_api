<?php

declare(strict_types=1);

namespace App\Pricing\Application\Message;

use App\Pricing\Domain\Model\FlashSaleId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Scheduled Message: Deactivate Flash Sale.
 *
 * This message is dispatched when a flash sale's end time is reached.
 * It triggers the deactivation (ending) of the flash sale, removing
 * special pricing from products.
 *
 * The message is scheduled when the flash sale is created, using
 * Symfony Messenger's delay feature to execute at the exact end time.
 */
final readonly class DeactivateFlashSaleMessage
{
    public function __construct(
        private string $flashSaleId,
        private string $tenantId,
    ) {
    }

    public function getFlashSaleId(): FlashSaleId
    {
        return FlashSaleId::fromString($this->flashSaleId);
    }

    public function getTenantId(): TenantId
    {
        return TenantId::fromString($this->tenantId);
    }
}
