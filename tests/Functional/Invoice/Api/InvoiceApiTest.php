<?php

declare(strict_types=1);

namespace App\Tests\Functional\Invoice\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Support\TenantTestTrait;
use Symfony\Component\Uid\Uuid;

/**
 * Functional Tests for Invoice API Endpoints.
 *
 * Tests Invoice lifecycle through the REST API:
 * - POST /api/v1/invoices (Create draft invoice)
 * - GET /api/v1/invoices/{id} (Get invoice)
 * - POST /api/v1/invoices/{id}/issue (Issue invoice)
 * - POST /api/v1/invoices/{id}/cancel (Cancel invoice)
 * - GET /api/v1/invoices/{id}/pdf (Download PDF)
 */
final class InvoiceApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();

        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();

        // Set RLS context
        $connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", self::DEFAULT_TENANT_ID)
        );

        // Clean up test data
        $connection->executeStatement('DELETE FROM invoice_lines');
        $connection->executeStatement('DELETE FROM invoices');
        $connection->executeStatement(
            'DELETE FROM invoice_sequences WHERE tenant_id = :tenantId',
            ['tenantId' => self::DEFAULT_TENANT_ID]
        );
    }

    // ============================================
    // POST /api/v1/invoices (Create Draft Invoice)
    // ============================================

    public function testCreateDraftInvoice(): void
    {
        $client = $this->createAuthenticatedClient();

        $orderId = Uuid::v4()->toString();
        $customerId = Uuid::v4()->toString();

        $response = $client->request('POST', '/api/v1/invoices', [
            'json' => $this->buildCreatePayload($orderId, $customerId),
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('draft', $data['status']);
        $this->assertSame($orderId, $data['orderId']);
        $this->assertSame($customerId, $data['customerId']);
        $this->assertNotEmpty($data['lines']);
        $this->assertCount(1, $data['lines']);
    }

    public function testCreateDraftInvoiceWithMultipleLines(): void
    {
        $client = $this->createAuthenticatedClient();

        $payload = $this->buildCreatePayload(Uuid::v4()->toString(), Uuid::v4()->toString());
        $payload['lines'][] = [
            'description' => 'Widget B',
            'quantity' => 3,
            'unitPriceAmount' => 500,
            'unitPriceCurrency' => 'EUR',
            'taxRate' => 21.0,
        ];

        $response = $client->request('POST', '/api/v1/invoices', [
            'json' => $payload,
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['lines']);
    }

    public function testCreateDraftInvoiceRejectsEmptyLines(): void
    {
        $client = $this->createAuthenticatedClient();

        $payload = $this->buildCreatePayload(Uuid::v4()->toString(), Uuid::v4()->toString());
        $payload['lines'] = [];

        $client->request('POST', '/api/v1/invoices', [
            'json' => $payload,
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateDraftInvoiceRejectsMissingOrderId(): void
    {
        $client = $this->createAuthenticatedClient();

        $payload = $this->buildCreatePayload(Uuid::v4()->toString(), Uuid::v4()->toString());
        unset($payload['orderId']);

        $client->request('POST', '/api/v1/invoices', [
            'json' => $payload,
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    // ============================================
    // GET /api/v1/invoices/{id} (Get Invoice)
    // ============================================

    public function testGetInvoiceById(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create invoice first
        $response = $client->request('POST', '/api/v1/invoices', [
            'json' => $this->buildCreatePayload(Uuid::v4()->toString(), Uuid::v4()->toString()),
        ]);

        $created = json_decode($response->getContent(), true);
        $invoiceId = $created['id'];

        // Get the invoice
        $response = $client->request('GET', '/api/v1/invoices/'.$invoiceId);

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($response->getContent(), true);
        $this->assertSame($invoiceId, $data['id']);
        $this->assertSame('draft', $data['status']);
    }

    public function testGetInvoiceReturns404ForNonExistent(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/v1/invoices/'.Uuid::v4()->toString());

        $this->assertResponseStatusCodeSame(404);
    }

    // ============================================
    // POST /api/v1/invoices/{id}/issue (Issue Invoice)
    // ============================================

    public function testIssueInvoice(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create draft
        $response = $client->request('POST', '/api/v1/invoices', [
            'json' => $this->buildCreatePayload(Uuid::v4()->toString(), Uuid::v4()->toString()),
        ]);
        $created = json_decode($response->getContent(), true);
        $invoiceId = $created['id'];

        // Issue it
        $response = $client->request('POST', '/api/v1/invoices/'.$invoiceId.'/issue', [
            'json' => new \stdClass(),
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('issued', $data['status']);
        $this->assertNotNull($data['invoiceNumber']);
        $this->assertStringStartsWith('INV-', $data['invoiceNumber']);
        $this->assertNotNull($data['issueDate']);
        $this->assertNotNull($data['dueDate']);
    }

    public function testIssueInvoiceReturns404ForNonExistent(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/v1/invoices/'.Uuid::v4()->toString().'/issue', [
            'json' => new \stdClass(),
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // ============================================
    // POST /api/v1/invoices/{id}/cancel (Cancel Invoice)
    // ============================================

    public function testCancelIssuedInvoice(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create and issue
        $response = $client->request('POST', '/api/v1/invoices', [
            'json' => $this->buildCreatePayload(Uuid::v4()->toString(), Uuid::v4()->toString()),
        ]);
        $created = json_decode($response->getContent(), true);
        $invoiceId = $created['id'];

        $client->request('POST', '/api/v1/invoices/'.$invoiceId.'/issue', [
            'json' => new \stdClass(),
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Cancel it
        $response = $client->request('POST', '/api/v1/invoices/'.$invoiceId.'/cancel', [
            'json' => new \stdClass(),
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('cancelled', $data['status']);
    }

    public function testCancelDraftInvoiceReturnsError(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create draft (not issued)
        $response = $client->request('POST', '/api/v1/invoices', [
            'json' => $this->buildCreatePayload(Uuid::v4()->toString(), Uuid::v4()->toString()),
        ]);
        $created = json_decode($response->getContent(), true);
        $invoiceId = $created['id'];

        // Try to cancel a draft
        $client->request('POST', '/api/v1/invoices/'.$invoiceId.'/cancel', [
            'json' => new \stdClass(),
        ]);

        // Should fail - can only cancel issued invoices
        $this->assertResponseStatusCodeSame(500);
    }

    public function testCancelReturns404ForNonExistent(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/v1/invoices/'.Uuid::v4()->toString().'/cancel', [
            'json' => new \stdClass(),
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // ============================================
    // GET /api/v1/invoices/{id}/pdf (Download PDF)
    // ============================================

    public function testDownloadPdfForIssuedInvoice(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create and issue
        $response = $client->request('POST', '/api/v1/invoices', [
            'json' => $this->buildCreatePayload(Uuid::v4()->toString(), Uuid::v4()->toString()),
        ]);
        $created = json_decode($response->getContent(), true);
        $invoiceId = $created['id'];

        $client->request('POST', '/api/v1/invoices/'.$invoiceId.'/issue', [
            'json' => new \stdClass(),
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Download PDF
        $response = $client->request('GET', '/api/v1/invoices/'.$invoiceId.'/pdf', [
            'headers' => [
                'Accept' => 'application/pdf',
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('content-type', 'application/pdf');
    }

    // ============================================
    // GET /api/v1/invoices (List Invoices)
    // ============================================

    public function testListInvoicesReturnsCollection(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create two invoices
        $client->request('POST', '/api/v1/invoices', [
            'json' => $this->buildCreatePayload(Uuid::v4()->toString(), Uuid::v4()->toString()),
        ]);
        $this->assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/v1/invoices', [
            'json' => $this->buildCreatePayload(Uuid::v4()->toString(), Uuid::v4()->toString()),
        ]);
        $this->assertResponseStatusCodeSame(201);

        // List them
        $response = $client->request('GET', '/api/v1/invoices');

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($response->getContent(), true);
        // API Platform wraps collections in hydra format
        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThanOrEqual(2, count($data['member']));
    }

    // ============================================
    // Authentication
    // ============================================

    public function testUnauthenticatedRequestReturns401(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/invoices', [
            'headers' => [
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // ============================================
    // Helpers
    // ============================================

    private function createAuthenticatedClient(
        string $email = 'invoice-admin@admin.com',
        array $roles = ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER'],
    ) {
        $tempClient = static::createClient();
        $container = $tempClient->getContainer();

        $em = $container->get('doctrine')->getManager();
        $userRepository = $em->getRepository(
            \App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity::class
        );

        if (!$userRepository->findOneBy(['email' => $email])) {
            $userEntity = new \App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity();
            $userEntity->setId(Uuid::v4()->toString());
            $userEntity->setEmail($email);
            $userEntity->setUsername(explode('@', $email)[0].'-'.bin2hex(random_bytes(4)));
            $userEntity->setPassword('$2y$13$dummy.password.hash');
            $userEntity->setRoles($roles);
            $userEntity->setCreatedAt(new \DateTimeImmutable());

            $em->persist($userEntity);
            $em->flush();
        }

        $encoder = $container->get('lexik_jwt_authentication.encoder');
        $token = $encoder->encode([
            'email' => $email,
            'roles' => $roles,
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        return static::createClient([], [
            'headers' => [
                'authorization' => 'Bearer '.$token,
                'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
            ],
        ]);
    }

    private function buildCreatePayload(string $orderId, string $customerId): array
    {
        return [
            'orderId' => $orderId,
            'customerId' => $customerId,
            'billingAddress' => [
                'name' => 'John Doe',
                'addressLine1' => '123 Main Street',
                'addressLine2' => null,
                'city' => 'Amsterdam',
                'postalCode' => '1011 AB',
                'country' => 'NL',
                'vatNumber' => null,
            ],
            'lines' => [
                [
                    'description' => 'Widget A',
                    'quantity' => 2,
                    'unitPriceAmount' => 1000,
                    'unitPriceCurrency' => 'EUR',
                    'taxRate' => 21.0,
                ],
            ],
            'isReverseCharge' => false,
            'notes' => 'Test invoice',
        ];
    }
}
