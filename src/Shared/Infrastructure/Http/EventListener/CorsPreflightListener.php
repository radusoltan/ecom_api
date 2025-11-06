<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CORS Preflight Listener
 *
 * Intercepts OPTIONS requests and returns 200 OK immediately
 * to satisfy browser CORS preflight requirements.
 *
 * NelmioCorsBundle handles the CORS headers, but Symfony
 * may return 400/500 errors for OPTIONS requests when
 * validation fails, which breaks CORS preflight.
 */
final class CorsPreflightListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Priority 251 to run BEFORE NelmioCorsListener (priority 250)
        // We set 200 OK response early, then NelmioCors adds CORS headers
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 251],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        error_log(sprintf('[CorsPreflightListener] Method: %s, Path: %s', $request->getMethod(), $request->getPathInfo()));

        // Only handle OPTIONS requests
        if ($request->getMethod() !== 'OPTIONS') {
            error_log('[CorsPreflightListener] Not OPTIONS, skipping');
            return;
        }

        // Only handle API routes (avoid interfering with other routes)
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            error_log('[CorsPreflightListener] Not API route, skipping');
            return;
        }

        error_log('[CorsPreflightListener] Returning 200 OK for preflight');

        // Return empty 200 OK response with CORS headers
        $response = new Response('', Response::HTTP_OK);

        // Add CORS headers manually since we're bypassing NelmioCors
        $origin = $request->headers->get('Origin') ?? '*';
        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:3001',
            'http://localhost:3004',
            'http://localhost:3005',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:3001',
            'http://127.0.0.1:3004',
            'http://127.0.0.1:3005',
            'http://192.168.88.146:3000',
            'http://192.168.88.146:3001',
            'http://192.168.88.146:3004',
            'http://192.168.88.146:3005',
            'http://192.168.88.159:3000',
            'http://192.168.88.159:3001',
            'http://192.168.88.159:3004',
            'http://192.168.88.159:3005',
            'http://ecom.local'
        ];

        if (in_array($origin, $allowedOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, Accept-Language, X-Tenant-ID, X-Cart-ID');
            $response->headers->set('Access-Control-Max-Age', '3600');
        }

        $event->setResponse($response);
        // CRITICAL: Stop event propagation to prevent API Platform from processing this request
        $event->stopPropagation();

        error_log('[CorsPreflightListener] Response set with CORS headers, propagation stopped');
    }
}
