<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

/**
 * API Redirect Controller
 *
 * Provides backward compatibility by redirecting /api/* to /api/v1/*
 *
 * This allows gradual migration of API consumers without breaking changes.
 * In future versions, this can be removed or updated to redirect to newer versions.
 *
 * HTTP 308 (Permanent Redirect) is used to preserve the HTTP method (POST, PATCH, etc.)
 *
 * @see EPIC 3.1 - API Versioning
 */
final class ApiRedirectController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(Request $request, string $path): RedirectResponse
    {
        // Redirect /api/* to /api/v1/*
        $newUrl = '/api/v1/' . $path;

        // Preserve query parameters
        if ($request->getQueryString()) {
            $newUrl .= '?' . $request->getQueryString();
        }

        $this->logger->info('API redirect from /api to /api/v1', [
            'original_path' => $request->getPathInfo(),
            'redirect_to' => $newUrl,
            'method' => $request->getMethod(),
            'client_ip' => $request->getClientIp(),
        ]);

        // HTTP 308 Permanent Redirect preserves the HTTP method
        return new RedirectResponse($newUrl, Response::HTTP_PERMANENTLY_REDIRECT);
    }
}
