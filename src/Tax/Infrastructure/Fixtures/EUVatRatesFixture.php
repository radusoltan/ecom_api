<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Fixtures;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Command\CreateTaxRule;
use App\Tax\Domain\ValueObject\TaxRuleId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * EU VAT Rates Fixture.
 *
 * Seeds standard VAT rates for all EU member states as of 2025.
 * These are standard rates; reduced rates would need separate rules.
 *
 * Source: European Commission VAT Rates
 * https://taxation-customs.ec.europa.eu/taxation-1/value-added-tax-vat_en
 */
final class EUVatRatesFixture extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['tax', 'eu', 'production'];
    }

    /**
     * EU VAT Standard Rates (2025).
     *
     * @var array<string, array{name: string, rate: float}>
     */
    private const EU_VAT_RATES = [
        // Western Europe
        'FR' => ['name' => 'TVA (France)', 'rate' => 20.0],
        'DE' => ['name' => 'MwSt (Germany)', 'rate' => 19.0],
        'IT' => ['name' => 'IVA (Italy)', 'rate' => 22.0],
        'ES' => ['name' => 'IVA (Spain)', 'rate' => 21.0],
        'NL' => ['name' => 'BTW (Netherlands)', 'rate' => 21.0],
        'BE' => ['name' => 'TVA/BTW (Belgium)', 'rate' => 21.0],
        'AT' => ['name' => 'USt (Austria)', 'rate' => 20.0],
        'PT' => ['name' => 'IVA (Portugal)', 'rate' => 23.0],

        // Nordic Countries
        'SE' => ['name' => 'Moms (Sweden)', 'rate' => 25.0],
        'DK' => ['name' => 'Moms (Denmark)', 'rate' => 25.0],
        'FI' => ['name' => 'ALV (Finland)', 'rate' => 25.5],

        // Eastern Europe
        'PL' => ['name' => 'VAT (Poland)', 'rate' => 23.0],
        'RO' => ['name' => 'TVA (Romania)', 'rate' => 19.0],
        'CZ' => ['name' => 'DPH (Czech Republic)', 'rate' => 21.0],
        'HU' => ['name' => 'ÁFA (Hungary)', 'rate' => 27.0],
        'BG' => ['name' => 'ДДС (Bulgaria)', 'rate' => 20.0],
        'SK' => ['name' => 'DPH (Slovakia)', 'rate' => 20.0],
        'HR' => ['name' => 'PDV (Croatia)', 'rate' => 25.0],
        'SI' => ['name' => 'DDV (Slovenia)', 'rate' => 22.0],

        // Baltic States
        'EE' => ['name' => 'KM (Estonia)', 'rate' => 22.0],
        'LV' => ['name' => 'PVN (Latvia)', 'rate' => 21.0],
        'LT' => ['name' => 'PVM (Lithuania)', 'rate' => 21.0],

        // Other EU Members
        'IE' => ['name' => 'VAT (Ireland)', 'rate' => 23.0],
        'GR' => ['name' => 'ΦΠΑ (Greece)', 'rate' => 24.0],
        'LU' => ['name' => 'TVA (Luxembourg)', 'rate' => 17.0],
        'MT' => ['name' => 'VAT (Malta)', 'rate' => 18.0],
        'CY' => ['name' => 'ΦΠΑ (Cyprus)', 'rate' => 19.0],
    ];

    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Get first tenant for seeding (in production, create rules per tenant via API)
        $tenantId = TenantId::fromString('9efae4ea-94fc-4807-b1bc-5e495ee7858c'); // Default test tenant

        echo "🏛️  Creating EU VAT tax rules...\n";

        foreach (self::EU_VAT_RATES as $countryCode => $data) {
            $this->createTaxRule(
                tenantId: $tenantId,
                countryCode: $countryCode,
                name: $data['name'],
                rate: $data['rate']
            );

            echo "  ✓ {$countryCode}: {$data['name']} ({$data['rate']}%)\n";
        }

        echo '✅ Created '.count(self::EU_VAT_RATES)." EU VAT tax rules\n\n";
    }

    private function createTaxRule(
        TenantId $tenantId,
        string $countryCode,
        string $name,
        float $rate,
    ): void {
        $command = new CreateTaxRule(
            id: TaxRuleId::generate(),
            tenantId: $tenantId,
            name: $name,
            countryCode: $countryCode,
            regionCode: null,
            ratePercentage: $rate
        );

        $this->commandBus->dispatch($command);
    }
}
