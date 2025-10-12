<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Elasticsearch;

use App\Internationalization\Domain\Model\Locale;

/**
 * Manages synonym mappings for search optimization.
 *
 * Business Rules:
 * - Synonyms are locale-specific
 * - Bidirectional synonyms (A => B implies B => A)
 * - Support multi-word synonyms
 * - Case-insensitive matching
 */
final readonly class SynonymManager
{
    /**
     * Get synonym mappings for a specific locale.
     *
     * @return array<string> Array of synonym rules in Solr format
     */
    public function getSynonymsForLocale(Locale $locale): array
    {
        return match ($locale->getLanguage()) {
            'en' => $this->getEnglishSynonyms(),
            'ro' => $this->getRomanianSynonyms(),
            'de' => $this->getGermanSynonyms(),
            'fr' => $this->getFrenchSynonyms(),
            'es' => $this->getSpanishSynonyms(),
            'it' => $this->getItalianSynonyms(),
            default => [],
        };
    }

    /**
     * @return array<string>
     */
    private function getEnglishSynonyms(): array
    {
        return [
            // Electronics
            'laptop, notebook, portable computer',
            'smartphone, mobile phone, cellphone, cell phone',
            'tablet, ipad',
            'headphones, headset, earphones, earbuds',
            'monitor, display, screen',
            'keyboard, keypad',
            'mouse, pointing device',

            // Clothing
            'shirt, tshirt, tee, top',
            'pants, trousers, jeans',
            'shoes, footwear, sneakers',
            'jacket, coat, outerwear',
            'dress, gown',

            // General
            'buy, purchase, order',
            'cheap, inexpensive, affordable, budget',
            'expensive, premium, high-end, luxury',
            'new, latest, newest, recent',
            'sale, discount, offer, deal',
            'fast, quick, rapid, speedy',
            'large, big, xl, extra large',
            'small, mini, compact, tiny',

            // Colors
            'red, crimson, scarlet',
            'blue, navy, azure',
            'green, emerald',
            'black, dark',
            'white, light',

            // Brands (generic)
            'computer, pc, desktop',
            'wireless, wifi, wi-fi',
            'waterproof, water-resistant',
        ];
    }

    /**
     * @return array<string>
     */
    private function getRomanianSynonyms(): array
    {
        return [
            // Electronice
            'laptop, notebook, calculator portabil',
            'smartphone, telefon mobil, telefon',
            'tableta, ipad',
            'casti, audiofon',
            'monitor, display, ecran',
            'tastatura, keyboard',
            'mouse, șoarece',

            // Îmbrăcăminte
            'tricou, bluza, top',
            'pantaloni, blugi, jeans',
            'pantofi, incaltaminte, adidasi',
            'jacheta, haina',

            // General
            'cumpara, comanda, achizitioneaza',
            'ieftin, accesibil, economic',
            'scump, premium, lux',
            'nou, recent, ultimul',
            'reducere, discount, oferta, promotie',
            'rapid, repede, viteza',
            'mare, xl, extra large',
            'mic, mini, compact',

            // Culori
            'rosu, carmin',
            'albastru, navy',
            'verde, smarald',
            'negru, inchis',
            'alb, deschis',
        ];
    }

    /**
     * @return array<string>
     */
    private function getGermanSynonyms(): array
    {
        return [
            // Elektronik
            'laptop, notebook, tragbarer computer',
            'smartphone, mobiltelefon, handy',
            'tablet, ipad',
            'kopfhörer, headset, ohrhörer',
            'monitor, bildschirm, display',
            'tastatur, keyboard',
            'maus, mouse',

            // Kleidung
            'hemd, shirt, tshirt',
            'hose, jeans',
            'schuhe, sneaker, turnschuhe',
            'jacke, mantel',

            // Allgemein
            'kaufen, bestellen, erwerben',
            'günstig, billig, preiswert',
            'teuer, premium, luxus',
            'neu, neueste, aktuell',
            'rabatt, angebot, sale',
            'schnell, rasch, zügig',
            'groß, xl, extra groß',
            'klein, mini, kompakt',
        ];
    }

    /**
     * @return array<string>
     */
    private function getFrenchSynonyms(): array
    {
        return [
            // Électronique
            'laptop, ordinateur portable, portable',
            'smartphone, téléphone mobile, portable',
            'tablette, ipad',
            'casque, écouteurs',
            'moniteur, écran, display',
            'clavier, keyboard',
            'souris, mouse',

            // Vêtements
            'chemise, tshirt, haut',
            'pantalon, jean',
            'chaussures, baskets, sneakers',
            'veste, manteau',

            // Général
            'acheter, commander, acquérir',
            'bon marché, pas cher, abordable',
            'cher, premium, luxe',
            'nouveau, récent, dernier',
            'réduction, promo, offre, solde',
            'rapide, vite, rapide',
            'grand, xl, extra large',
            'petit, mini, compact',
        ];
    }

    /**
     * @return array<string>
     */
    private function getSpanishSynonyms(): array
    {
        return [
            // Electrónica
            'laptop, portátil, ordenador portátil',
            'smartphone, teléfono móvil, móvil',
            'tableta, tablet, ipad',
            'auriculares, cascos, audífonos',
            'monitor, pantalla, display',
            'teclado, keyboard',
            'ratón, mouse',

            // Ropa
            'camisa, camiseta, playera',
            'pantalones, jeans, vaqueros',
            'zapatos, zapatillas, calzado',
            'chaqueta, abrigo',

            // General
            'comprar, adquirir, ordenar',
            'barato, económico, asequible',
            'caro, premium, lujo',
            'nuevo, reciente, último',
            'descuento, oferta, rebaja',
            'rápido, veloz',
            'grande, xl, extra grande',
            'pequeño, mini, compacto',
        ];
    }

    /**
     * @return array<string>
     */
    private function getItalianSynonyms(): array
    {
        return [
            // Elettronica
            'laptop, notebook, computer portatile',
            'smartphone, telefono cellulare, cellulare',
            'tablet, ipad',
            'cuffie, auricolari',
            'monitor, schermo, display',
            'tastiera, keyboard',
            'mouse, topo',

            // Abbigliamento
            'camicia, maglietta, tshirt',
            'pantaloni, jeans',
            'scarpe, calzature, sneakers',
            'giacca, cappotto',

            // Generale
            'comprare, acquistare, ordinare',
            'economico, conveniente, a buon mercato',
            'costoso, premium, lusso',
            'nuovo, recente, ultimo',
            'sconto, offerta, promozione',
            'veloce, rapido',
            'grande, xl, extra large',
            'piccolo, mini, compatto',
        ];
    }
}
