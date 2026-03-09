<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Telemetry;

use OpenTelemetry\API\Common\Time\SystemClock;
use OpenTelemetry\API\Trace\NoopTracerProvider as ApiNoopTracerProvider;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

final class TracerFactory
{
    private ?TracerProviderInterface $tracerProvider = null;

    public function __construct(
        private readonly string $serviceName,
        private readonly string $serviceVersion,
        private readonly string $environment,
        private readonly string $otlpEndpoint,
        private readonly float $sampleRate = 1.0,
    ) {
    }

    public function createTracer(string $name = 'ecom-api'): TracerInterface
    {
        return $this->getTracerProvider()->getTracer($name, $this->serviceVersion);
    }

    public function getTracerProvider(): TracerProviderInterface
    {
        if (null === $this->tracerProvider) {
            $this->tracerProvider = $this->buildTracerProvider();
        }

        return $this->tracerProvider;
    }

    public function shutdown(): void
    {
        if ($this->tracerProvider instanceof TracerProvider) {
            $this->tracerProvider->shutdown();
        }
    }

    private function buildTracerProvider(): TracerProviderInterface
    {
        if ($this->sampleRate <= 0.0 || '' === $this->otlpEndpoint) {
            return new ApiNoopTracerProvider();
        }

        // Check if the collector endpoint is reachable before creating the exporter
        if (!$this->isCollectorReachable()) {
            return new ApiNoopTracerProvider();
        }

        $resource = ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => $this->serviceName,
            ResourceAttributes::SERVICE_VERSION => $this->serviceVersion,
            ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => $this->environment,
        ]));

        $transport = (new OtlpHttpTransportFactory())->create(
            $this->otlpEndpoint.'/v1/traces',
            'application/x-protobuf',
        );

        $exporter = new SpanExporter($transport);

        $sampler = $this->sampleRate >= 1.0
            ? new AlwaysOnSampler()
            : new ParentBased(new TraceIdRatioBasedSampler($this->sampleRate));

        return new TracerProvider(
            spanProcessors: [new BatchSpanProcessor($exporter, SystemClock::create())],
            sampler: $sampler,
            resource: $resource,
        );
    }

    private function isCollectorReachable(): bool
    {
        $parts = parse_url($this->otlpEndpoint);
        if (false === $parts || !isset($parts['host'])) {
            return false;
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? ('https' === ($parts['scheme'] ?? 'http') ? 443 : 80);

        $connection = @fsockopen($host, $port, $errno, $errstr, 1.0);
        if (false === $connection) {
            return false;
        }

        fclose($connection);

        return true;
    }
}
