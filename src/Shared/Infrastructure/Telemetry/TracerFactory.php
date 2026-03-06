<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Telemetry;

use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
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
            spanProcessors: [new SimpleSpanProcessor($exporter)],
            sampler: $sampler,
            resource: $resource,
        );
    }
}
