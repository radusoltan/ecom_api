<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\Twig;

use App\Shared\Domain\ValueObject\Money;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension for invoice-related formatting.
 *
 * Provides custom filters for formatting money values in invoice templates.
 */
final class InvoiceTwigExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->formatMoney(...)),
        ];
    }

    /**
     * Format money value for display in invoices.
     *
     * @param Money|int $amount   The amount to format (Money VO or cents as int)
     * @param string    $currency Currency code (used only if $amount is int)
     *
     * @return string Formatted money string (e.g., "1,234.56 EUR")
     */
    public function formatMoney(Money|int $amount, string $currency = 'EUR'): string
    {
        if ($amount instanceof Money) {
            $cents = $amount->getAmount();
            $currencyCode = $amount->getCurrency()->getCurrencyCode();
        } else {
            $cents = $amount;
            $currencyCode = $currency;
        }

        // Convert cents to decimal value
        $value = $cents / 100;

        // Format with 2 decimal places, using . for decimals and , for thousands
        return number_format($value, 2, '.', ',').' '.$currencyCode;
    }
}
