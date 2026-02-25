<?php

declare(strict_types=1);

namespace App\Tests\Integration\Invoice\Infrastructure\NumberGenerator;

use App\Invoice\Domain\Model\InvoiceNumber;
use App\Invoice\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SequentialInvoiceNumberGeneratorTest extends KernelTestCase
{
    use TenantTestTrait;

    private InvoiceNumberGeneratorInterface $generator;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());

        $container = static::getContainer();
        $this->generator = $container->get(InvoiceNumberGeneratorInterface::class);

        $this->cleanupSequences();
    }

    protected function tearDown(): void
    {
        $this->cleanupSequences();
        parent::tearDown();
    }

    private function cleanupSequences(): void
    {
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();

        $connection->executeStatement(
            'DELETE FROM invoice_sequences WHERE tenant_id = :tenantId',
            ['tenantId' => $this->tenantId->toString()]
        );
    }

    public function testGenerateReturnsFirstSequenceNumber(): void
    {
        $number = $this->generator->generate($this->tenantId, 2025);

        $this->assertInstanceOf(InvoiceNumber::class, $number);
        $this->assertSame('INV-2025-000001', $number->toString());
    }

    public function testGenerateIncrementsSequentially(): void
    {
        $number1 = $this->generator->generate($this->tenantId, 2025);
        $number2 = $this->generator->generate($this->tenantId, 2025);
        $number3 = $this->generator->generate($this->tenantId, 2025);

        $this->assertSame(1, $number1->sequence());
        $this->assertSame(2, $number2->sequence());
        $this->assertSame(3, $number3->sequence());
    }

    public function testGetCurrentSequenceReturnsZeroWhenNoSequence(): void
    {
        $current = $this->generator->getCurrentSequence($this->tenantId, 2099);

        $this->assertSame(0, $current);
    }

    public function testGetCurrentSequenceReturnsCurrentValue(): void
    {
        $this->generator->generate($this->tenantId, 2025);
        $this->generator->generate($this->tenantId, 2025);

        $current = $this->generator->getCurrentSequence($this->tenantId, 2025);

        $this->assertSame(2, $current);
    }

    public function testPreviewNextReturnsNextWithoutIncrementing(): void
    {
        $this->generator->generate($this->tenantId, 2025);

        $preview = $this->generator->previewNext($this->tenantId, 2025);

        $this->assertSame(2, $preview->sequence());

        // Verify sequence was NOT incremented
        $current = $this->generator->getCurrentSequence($this->tenantId, 2025);
        $this->assertSame(1, $current);
    }

    public function testSequenceIsPerYear(): void
    {
        $number2025 = $this->generator->generate($this->tenantId, 2025);
        $number2026 = $this->generator->generate($this->tenantId, 2026);

        $this->assertSame(1, $number2025->sequence());
        $this->assertSame(2025, $number2025->year());
        $this->assertSame(1, $number2026->sequence());
        $this->assertSame(2026, $number2026->year());
    }
}
