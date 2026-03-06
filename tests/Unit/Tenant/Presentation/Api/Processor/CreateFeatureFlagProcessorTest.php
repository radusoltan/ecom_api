<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use App\Tenant\Presentation\Api\Processor\CreateFeatureFlagProcessor;
use App\Tenant\Presentation\Api\Resource\FeatureFlagResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class CreateFeatureFlagProcessorTest extends TestCase
{
    private MessageBusInterface&MockObject $commandBus;
    private RequestStack&MockObject $requestStack;
    private CreateFeatureFlagProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->processor = new CreateFeatureFlagProcessor($this->commandBus, $this->requestStack);
    }

    public function testProcessWithTenantIdOnResource(): void
    {
        $data = new FeatureFlagResource();
        $data->tenantId = '00000000-0000-4000-8000-000000000001';
        $data->featureName = 'dark_mode';
        $data->enabled = true;
        $data->configuration = ['theme' => 'dark'];
        $data->description = 'Enable dark mode';

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $result = $this->processor->process($data, new Post());

        $this->assertInstanceOf(FeatureFlagResource::class, $result);
        $this->assertSame('00000000-0000-4000-8000-000000000001', $result->tenantId);
    }

    public function testProcessWithTenantIdFromContext(): void
    {
        $data = new FeatureFlagResource();
        $data->tenantId = null;
        $data->featureName = 'dark_mode';

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $result = $this->processor->process(
            $data,
            new Post(),
            [],
            ['tenant_id' => '00000000-0000-4000-8000-000000000002'],
        );

        $this->assertSame('00000000-0000-4000-8000-000000000002', $result->tenantId);
    }

    public function testProcessWithTenantIdFromRequestHeader(): void
    {
        $data = new FeatureFlagResource();
        $data->tenantId = null;
        $data->featureName = 'dark_mode';

        $request = $this->createMock(Request::class);
        $headerBag = new HeaderBag(['X-Tenant-ID' => '00000000-0000-4000-8000-000000000003']);
        $request->headers = $headerBag;
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $result = $this->processor->process($data, new Post());

        $this->assertSame('00000000-0000-4000-8000-000000000003', $result->tenantId);
    }

    public function testThrowsWhenNotFeatureFlagResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected FeatureFlagResource');

        $this->processor->process('not-a-resource', new Post());
    }

    public function testThrowsWhenTenantIdMissing(): void
    {
        $data = new FeatureFlagResource();
        $data->tenantId = null;
        $data->featureName = 'dark_mode';

        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant ID is required');

        $this->processor->process($data, new Post());
    }

    public function testThrowsWhenFeatureNameMissing(): void
    {
        $data = new FeatureFlagResource();
        $data->tenantId = '00000000-0000-4000-8000-000000000001';
        $data->featureName = null;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Feature name is required');

        $this->processor->process($data, new Post());
    }

    public function testDefaultEnabledIsFalse(): void
    {
        $data = new FeatureFlagResource();
        $data->tenantId = '00000000-0000-4000-8000-000000000001';
        $data->featureName = 'new_feature';
        $data->enabled = null;

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) {
                return false === $command->enabled;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->processor->process($data, new Post());
    }
}
