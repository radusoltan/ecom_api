<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Telemetry;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onRequest', priority: 2048)]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onResponse', priority: -2048)]
#[AsEventListener(event: KernelEvents::TERMINATE, method: 'onTerminate', priority: -2048)]
final class RequestTracingListener
{
    private ?SpanInterface $span = null;

    private const EXCLUDED_PATHS = [
        '/metrics',
        '/_profiler',
        '/_wdt',
        '/health',
    ];

    public function __construct(
        private readonly TracerInterface $tracer,
    ) {
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        foreach (self::EXCLUDED_PATHS as $excluded) {
            if (str_starts_with($path, $excluded)) {
                return;
            }
        }

        $method = $request->getMethod();
        $route = $request->attributes->get('_route', 'unknown');

        $this->span = $this->tracer->spanBuilder(sprintf('%s %s', $method, $route))
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('http.request.method', $method)
            ->setAttribute('url.path', $path)
            ->setAttribute('http.route', $route)
            ->setAttribute('server.address', $request->getHost())
            ->setAttribute('server.port', $request->getPort())
            ->setAttribute('user_agent.original', $request->headers->get('User-Agent', ''))
            ->startSpan();

        // Add tenant.id for multi-tenant trace filtering
        $tenantId = $request->headers->get('X-Tenant-ID');
        if ($tenantId) {
            $this->span->setAttribute('tenant.id', $tenantId);
        }

        // Add locale
        $locale = $request->headers->get('Accept-Language');
        if ($locale) {
            $this->span->setAttribute('http.request.header.accept_language', $locale);
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || $this->span === null) {
            return;
        }

        $statusCode = $event->getResponse()->getStatusCode();
        $this->span->setAttribute('http.response.status_code', $statusCode);

        if ($statusCode >= 500) {
            $this->span->setStatus(StatusCode::STATUS_ERROR, 'HTTP ' . $statusCode);
        } elseif ($statusCode >= 400) {
            $this->span->setStatus(StatusCode::STATUS_UNSET);
        } else {
            $this->span->setStatus(StatusCode::STATUS_OK);
        }
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if ($this->span === null) {
            return;
        }

        $this->span->end();
        $this->span = null;
    }
}
