<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Messenger;

use App\Shared\Infrastructure\Messenger\TenantStamp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\StampInterface;

#[CoversClass(TenantStamp::class)]
final class TenantStampTest extends TestCase
{
    #[Test]
    public function itImplementsStampInterface(): void
    {
        $stamp = new TenantStamp('00000000-0000-4000-8000-000000000001');

        self::assertInstanceOf(StampInterface::class, $stamp);
    }

    #[Test]
    public function itReturnsTenantId(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $stamp = new TenantStamp($tenantId);

        self::assertSame($tenantId, $stamp->getTenantId());
    }

    #[Test]
    public function itPreservesArbitraryTenantIdString(): void
    {
        $id = 'some-custom-id-value';
        $stamp = new TenantStamp($id);

        self::assertSame($id, $stamp->getTenantId());
    }
}
