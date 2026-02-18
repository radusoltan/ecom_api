<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Infrastructure\Security;

use App\User\Infrastructure\Security\MfaAccessListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(MfaAccessListener::class)]
final class MfaAccessListenerTest extends TestCase
{
    private MfaAccessListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listener = new MfaAccessListener();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeEvent(Request $request, bool $isMain = true): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $requestType = $isMain
            ? HttpKernelInterface::MAIN_REQUEST
            : HttpKernelInterface::SUB_REQUEST;

        return new RequestEvent($kernel, $request, $requestType);
    }

    private function makeRequest(string $path, bool $mfaPending = false): Request
    {
        $request = Request::create($path);
        if ($mfaPending) {
            $request->attributes->set('_mfa_pending', true);
        }

        return $request;
    }

    // -----------------------------------------------------------------------
    // Blocking behaviour
    // -----------------------------------------------------------------------

    #[Test]
    public function itBlocksRequestWithMfaPendingAttributeAndReturns403(): void
    {
        $request = $this->makeRequest('/api/products', mfaPending: true);
        $event = $this->makeEvent($request);

        ($this->listener)($event);

        self::assertTrue($event->hasResponse());
        self::assertInstanceOf(JsonResponse::class, $event->getResponse());
        self::assertSame(Response::HTTP_FORBIDDEN, $event->getResponse()->getStatusCode());
    }

    #[Test]
    public function itIncludesMfaErrorMessageInBlockedResponse(): void
    {
        $request = $this->makeRequest('/api/orders', mfaPending: true);
        $event = $this->makeEvent($request);

        ($this->listener)($event);

        $responseData = json_decode((string) $event->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $responseData);
        self::assertStringContainsString('MFA verification required', $responseData['error']);
    }

    // -----------------------------------------------------------------------
    // Allowed paths despite _mfa_pending
    // -----------------------------------------------------------------------

    #[Test]
    public function itAllowsAccessToMfaVerifyEndpointEvenWithMfaPending(): void
    {
        $request = $this->makeRequest('/api/mfa/verify', mfaPending: true);
        $event = $this->makeEvent($request);

        ($this->listener)($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function itAllowsAccessToMfaVerifyWithSubpathEvenWithMfaPending(): void
    {
        // Any path that starts with /api/mfa/verify should be allowed
        $request = $this->makeRequest('/api/mfa/verify/totp', mfaPending: true);
        $event = $this->makeEvent($request);

        ($this->listener)($event);

        self::assertFalse($event->hasResponse());
    }

    // -----------------------------------------------------------------------
    // Pass-through when _mfa_pending is not set
    // -----------------------------------------------------------------------

    #[Test]
    public function itDoesNothingWhenMfaPendingIsNotSet(): void
    {
        $request = $this->makeRequest('/api/products', mfaPending: false);
        $event = $this->makeEvent($request);

        ($this->listener)($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function itDoesNothingWhenMfaPendingIsFalse(): void
    {
        $request = $this->makeRequest('/api/orders');
        $request->attributes->set('_mfa_pending', false);
        $event = $this->makeEvent($request);

        ($this->listener)($event);

        self::assertFalse($event->hasResponse());
    }

    // -----------------------------------------------------------------------
    // Sub-request handling
    // -----------------------------------------------------------------------

    #[Test]
    public function itIgnoresSubRequests(): void
    {
        $request = $this->makeRequest('/api/products', mfaPending: true);
        $event = $this->makeEvent($request, isMain: false);

        ($this->listener)($event);

        // Sub-requests must always pass through, even with _mfa_pending
        self::assertFalse($event->hasResponse());
    }

    // -----------------------------------------------------------------------
    // Various protected endpoints
    // -----------------------------------------------------------------------

    #[Test]
    public function itBlocksApiCatalogEndpointWhenMfaPending(): void
    {
        $request = $this->makeRequest('/api/catalog/products', mfaPending: true);
        $event = $this->makeEvent($request);

        ($this->listener)($event);

        self::assertSame(Response::HTTP_FORBIDDEN, $event->getResponse()->getStatusCode());
    }

    #[Test]
    public function itBlocksRootApiEndpointWhenMfaPending(): void
    {
        $request = $this->makeRequest('/api', mfaPending: true);
        $event = $this->makeEvent($request);

        ($this->listener)($event);

        self::assertSame(Response::HTTP_FORBIDDEN, $event->getResponse()->getStatusCode());
    }
}
