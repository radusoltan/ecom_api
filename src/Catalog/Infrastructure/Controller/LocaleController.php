<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for locale-related endpoints
 */
final class LocaleController extends AbstractController
{
    /**
     * GET /api/locales
     * Returns available locales for the system
     */
    public function getAvailableLocales(Request $request): JsonResponse
    {
        // Get supported locales from configuration
        // In a real application, this might come from database or configuration
        $locales = [
            'en',      // English (base)
            'en_US',   // English (US)
            'fr',      // French (base)
            'fr_FR',   // French (France)
            'de',      // German (base)
            'de_DE',   // German (Germany)
            'ro',      // Romanian (base)
            'ro_RO',   // Romanian (Romania)
        ];

        return $this->json([
            'locales' => $locales,
            'default' => 'en_US',
            'fallback' => 'en',
        ]);
    }
}