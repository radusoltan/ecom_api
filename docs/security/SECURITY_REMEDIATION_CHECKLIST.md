# Security Remediation Checklist

**Audit Date**: 2025-12-05
**Target Completion**: 2025-12-15 (10 days)
**Status**: IN PROGRESS

---

## Phase 1: IMMEDIATE ACTIONS (Day 1)

### Task 1.1: Fix Public API Endpoints [CRITICAL - C04]
**Priority**: P0 - BLOCK PRODUCTION
**Assignee**: Backend Team Lead
**ETA**: 1 hour

- [ ] Create `config/packages/prod/security.yaml`
- [ ] Move development endpoints to `config/packages/dev/security.yaml`
- [ ] Update production config with restricted endpoints:

```yaml
# config/packages/prod/security.yaml
security:
    access_control:
        # PROTECTED: Admin/Manager only
        - { path: ^/api/v1/dashboard/stats, roles: ROLE_ADMIN }
        - { path: ^/api/v1/inventory/stats, roles: ROLE_MANAGER }
        - { path: ^/api/stock-items, roles: ROLE_MANAGER }
        - { path: ^/api/v1/stock-items, roles: ROLE_MANAGER }
        - { path: ^/api/v1/variant_entities, roles: ROLE_MANAGER }
        - { path: ^/api/variant_entities, roles: ROLE_MANAGER }
        - { path: ^/api/v1/product-options, roles: ROLE_MANAGER }
        - { path: ^/api/product-options, roles: ROLE_MANAGER }
        - { path: ^/api/v1/product-option-values, roles: ROLE_MANAGER }
        - { path: ^/api/product-option-values, roles: ROLE_MANAGER }
```

- [ ] Test in development: Verify endpoints still accessible
- [ ] Deploy to staging: Verify endpoints require authentication
- [ ] Document change in ADR

**Verification**:
```bash
# Without JWT token (should fail)
curl http://localhost:8000/api/v1/dashboard/stats
# Expected: 401 Unauthorized

# With JWT token (should succeed)
curl -H "Authorization: Bearer $JWT" http://localhost:8000/api/v1/dashboard/stats
# Expected: 200 OK
```

---

### Task 1.2: Add JWT Token Expiry [HIGH - H01]
**Priority**: P1
**Assignee**: Backend Developer
**ETA**: 15 minutes

- [ ] Update `config/packages/lexik_jwt_authentication.yaml`

```yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    user_id_claim: email
    token_ttl: 3600  # 1 hour (recommended)

    # Token extraction from cookie
    token_extractors:
        authorization_header:
            enabled: true
            prefix: Bearer
            name: Authorization
        cookie:
            enabled: true
            name: auth-token
```

- [ ] Clear Symfony cache: `symfony console cache:clear`
- [ ] Test token expiry: Login, wait 1 hour, verify token expired
- [ ] Document in API documentation

**Verification**:
```bash
# Login and extract JWT
JWT=$(curl -X POST http://localhost:8000/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"username":"admin@example.com","password":"password"}' \
  | jq -r .token)

# Decode JWT and check exp claim
echo $JWT | cut -d. -f2 | base64 -d | jq .exp
# Should be: current_timestamp + 3600
```

---

### Task 1.3: Replace CORS Wildcards [HIGH - H02]
**Priority**: P1
**Assignee**: DevOps Engineer
**ETA**: 30 minutes

- [ ] Update `.env.prod` or production environment variables

```bash
# .env.prod
CORS_ALLOW_ORIGIN='^https://(storefront\.example\.com|admin\.example\.com)$'
```

- [ ] Update `config/packages/prod/nelmio_cors.yaml`

```yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_methods: ['GET', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE']
        allow_headers: ['Content-Type', 'Authorization', 'Accept', 'Accept-Language', 'X-Tenant-ID']
        expose_headers: ['Link', 'Content-Language']
        allow_credentials: true
        max_age: 3600
```

- [ ] Test in staging with allowed origin: Should succeed
- [ ] Test in staging with disallowed origin: Should fail with CORS error
- [ ] Document allowed origins in runbook

**Verification**:
```bash
# Test with allowed origin
curl -H "Origin: https://storefront.example.com" \
  http://localhost:8000/api/v1/categories
# Expected: Access-Control-Allow-Origin: https://storefront.example.com

# Test with disallowed origin
curl -H "Origin: https://evil.com" \
  http://localhost:8000/api/v1/categories
# Expected: No Access-Control-Allow-Origin header (CORS blocks in browser)
```

---

### Task 1.4: Enable Forced SSL in Production [HIGH - H03]
**Priority**: P1
**Assignee**: DevOps Engineer
**ETA**: 15 minutes

- [ ] Update `config/packages/prod/nelmio_security.yaml`

```yaml
nelmio_security:
    forced_ssl:
        enabled: true  # Enable in production
        hsts_max_age: 31536000  # 1 year
        hsts_subdomains: true
        hsts_preload: true
        redirect_status_code: 301  # Permanent redirect
```

- [ ] Configure Nginx/Apache for TLS 1.3

```nginx
# /etc/nginx/sites-available/api.ecom.local
server {
    listen 443 ssl http2;
    server_name api.example.com;

    ssl_certificate /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;
    ssl_protocols TLSv1.3 TLSv1.2;
    ssl_prefer_server_ciphers on;
    ssl_ciphers 'ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384';

    # ... rest of config
}
```

- [ ] Test HTTPS redirect: `curl -I http://api.example.com`
- [ ] Test TLS version: `openssl s_client -connect api.example.com:443 -tls1_3`
- [ ] Verify HSTS header: `curl -I https://api.example.com | grep Strict-Transport-Security`

**Verification**:
```bash
# Test HTTP redirect
curl -I http://api.example.com
# Expected: 301 Moved Permanently, Location: https://api.example.com

# Test HSTS header
curl -I https://api.example.com
# Expected: Strict-Transport-Security: max-age=31536000; includeSubDomains; preload

# Test TLS version
nmap --script ssl-enum-ciphers -p 443 api.example.com
# Expected: TLSv1.3 supported
```

---

### Task 1.5: Move Secrets to Vault [CRITICAL - C05]
**Priority**: P0 - BLOCK PRODUCTION
**Assignee**: DevOps Engineer
**ETA**: 1 day

**Option A: Symfony Secrets Vault (Recommended for now)**

- [ ] Initialize secrets vault

```bash
# Development
php bin/console secrets:set DATABASE_URL --env=dev
php bin/console secrets:set JWT_PASSPHRASE --env=dev
php bin/console secrets:set STRIPE_SECRET_KEY --env=dev
php bin/console secrets:set PAYPAL_CLIENT_SECRET --env=dev
php bin/console secrets:set ELASTICSEARCH_PASSWORD --env=dev
php bin/console secrets:set MERCURE_JWT_SECRET --env=dev
php bin/console secrets:set MESSENGER_TRANSPORT_DSN --env=dev
php bin/console secrets:set TWO_CHECKOUT_SECRET_KEY --env=dev

# Production
php bin/console secrets:set DATABASE_URL --env=prod
php bin/console secrets:set JWT_PASSPHRASE --env=prod
php bin/console secrets:set STRIPE_SECRET_KEY --env=prod
php bin/console secrets:set PAYPAL_CLIENT_SECRET --env=prod
php bin/console secrets:set ELASTICSEARCH_PASSWORD --env=prod
php bin/console secrets:set MERCURE_JWT_SECRET --env=prod
php bin/console secrets:set MESSENGER_TRANSPORT_DSN --env=prod
php bin/console secrets:set TWO_CHECKOUT_SECRET_KEY --env=prod
```

- [ ] Update `.env` to remove secret values

```bash
# .env (commit this)
DATABASE_URL=
JWT_PASSPHRASE=
STRIPE_SECRET_KEY=
PAYPAL_CLIENT_SECRET=
ELASTICSEARCH_PASSWORD=
MERCURE_JWT_SECRET=
MESSENGER_TRANSPORT_DSN=
TWO_CHECKOUT_SECRET_KEY=
```

- [ ] Test application still works with secrets from vault
- [ ] Commit changes: `git add config/secrets/`
- [ ] Document secret management in runbook

**Option B: Cloud Key Management (For production)**

AWS Secrets Manager:
```bash
# Store secrets
aws secretsmanager create-secret --name ecom/database-url --secret-string "$DATABASE_URL"
aws secretsmanager create-secret --name ecom/jwt-passphrase --secret-string "$JWT_PASSPHRASE"
aws secretsmanager create-secret --name ecom/stripe-secret-key --secret-string "$STRIPE_SECRET_KEY"

# Update .env.prod to fetch from AWS
DATABASE_URL="$(aws secretsmanager get-secret-value --secret-id ecom/database-url --query SecretString --output text)"
```

- [ ] Choose option A (Symfony) or B (Cloud)
- [ ] Implement chosen option
- [ ] Rotate all exposed secrets (DB password, API keys, JWT keys)
- [ ] Verify no secrets in git history: `git log -p | grep -i "password\|secret\|api_key"`

**Verification**:
```bash
# Test secrets loaded correctly
php bin/console debug:container --env=prod --parameter=kernel.secret
# Expected: Value loaded from vault, not from .env

# Verify .env has no secrets
cat .env | grep -E "PASSWORD|SECRET|KEY" | grep -v "^#"
# Expected: All values empty or referencing vault
```

**CRITICAL**: After moving secrets, rotate all credentials immediately:
- [ ] Change database password
- [ ] Regenerate JWT key pair
- [ ] Rotate Stripe webhook secret
- [ ] Rotate PayPal client secret
- [ ] Change Elasticsearch password

---

## Phase 2: CRITICAL FEATURES (Days 2-7)

### Task 2.1: Implement MFA/TOTP [CRITICAL - C01]
**Priority**: P0 - BLOCK PRODUCTION
**Assignee**: Senior Backend Developer
**ETA**: 2-3 days

**Day 1: Setup & Schema**

- [ ] Install dependencies

```bash
composer require scheb/2fa-bundle
composer require scheb/2fa-totp
composer require endroid/qr-code
```

- [ ] Create migration for MFA fields

```php
// migrations/Version20251206000000_AddMFAToUsers.php
public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE users ADD totp_secret VARCHAR(255) DEFAULT NULL');
    $this->addSql('ALTER TABLE users ADD mfa_enabled BOOLEAN DEFAULT FALSE');
    $this->addSql('ALTER TABLE users ADD backup_codes JSON DEFAULT NULL');
    $this->addSql('ALTER TABLE users ADD mfa_enabled_at TIMESTAMP DEFAULT NULL');
}
```

- [ ] Run migration: `symfony console doctrine:migrations:migrate`

**Day 2: Domain & Application Layer**

- [ ] Add MFA value objects

```php
// src/User/Domain/ValueObject/TotpSecret.php
final readonly class TotpSecret {
    public static function generate(): self {
        return new self(
            base32_encode(random_bytes(20))
        );
    }

    public function toString(): string {
        return $this->value;
    }
}
```

- [ ] Update User aggregate with MFA methods

```php
// src/User/Domain/Model/User.php
public function enableMfa(TotpSecret $secret): void
{
    if ($this->mfaEnabled) {
        throw new \DomainException('MFA already enabled');
    }

    $this->totpSecret = $secret;
    $this->mfaEnabled = true;
    $this->mfaEnabledAt = new \DateTimeImmutable();
    $this->generateBackupCodes();
}

private function generateBackupCodes(): void
{
    $codes = [];
    for ($i = 0; $i < 10; $i++) {
        $codes[] = bin2hex(random_bytes(4)); // 8-character codes
    }
    $this->backupCodes = $codes;
}
```

- [ ] Create MFA commands

```php
// src/User/Application/Command/EnableMfa/EnableMfaCommand.php
// src/User/Application/Command/EnableMfa/EnableMfaHandler.php
// src/User/Application/Command/DisableMfa/DisableMfaCommand.php
// src/User/Application/Command/DisableMfa/DisableMfaHandler.php
// src/User/Application/Command/VerifyMfa/VerifyMfaCommand.php
// src/User/Application/Command/VerifyMfa/VerifyMfaHandler.php
```

**Day 3: API Endpoints & Testing**

- [ ] Create MFA API endpoints

```php
// src/User/Presentation/Api/Controller/MfaController.php
#[Route('/api/v1/auth/mfa', name: 'api_mfa_')]
class MfaController extends AbstractController
{
    #[Route('/enroll', methods: ['POST'])]
    public function enroll(): JsonResponse {
        // Generate TOTP secret
        // Return QR code + manual entry code
    }

    #[Route('/verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse {
        // Verify TOTP code
        // Enable MFA if valid
        // Return backup codes
    }

    #[Route('/disable', methods: ['POST'])]
    public function disable(Request $request): JsonResponse {
        // Require password confirmation
        // Disable MFA
    }

    #[Route('/backup-codes', methods: ['GET'])]
    public function getBackupCodes(): JsonResponse {
        // Return backup codes (only once)
    }
}
```

- [ ] Configure scheb/2fa-bundle

```yaml
# config/packages/scheb_2fa.yaml
scheb_two_factor:
    totp:
        enabled: true
        server_name: 'E-Commerce Platform'
        issuer: 'ecom.example.com'
        window: 1  # Allow 1 time-step before/after current
```

- [ ] Write tests

```php
// tests/Functional/User/MfaTest.php
// - testEnrollMfa()
// - testVerifyMfaWithValidCode()
// - testVerifyMfaWithInvalidCode()
// - testVerifyMfaWithBackupCode()
// - testDisableMfa()
// - testLoginWithMfaRequired()
```

- [ ] Update login flow to check MFA status
- [ ] Test end-to-end: Enroll → QR code → Authenticator app → Login with MFA
- [ ] Document MFA flow in API docs

**Verification**:
```bash
# Test MFA enrollment
curl -X POST http://localhost:8000/api/v1/auth/mfa/enroll \
  -H "Authorization: Bearer $JWT"
# Expected: QR code data URL + TOTP secret

# Test MFA verification
curl -X POST http://localhost:8000/api/v1/auth/mfa/verify \
  -H "Authorization: Bearer $JWT" \
  -H "Content-Type: application/json" \
  -d '{"code":"123456"}'
# Expected: 200 OK + backup codes

# Test login with MFA
curl -X POST http://localhost:8000/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"username":"admin@example.com","password":"password"}'
# Expected: 200 OK + "mfa_required": true + "mfa_token": "..."

curl -X POST http://localhost:8000/api/v1/auth/mfa/verify-login \
  -H "Content-Type: application/json" \
  -d '{"mfa_token":"...","code":"123456"}'
# Expected: 200 OK + JWT token
```

---

### Task 2.2: Implement Idempotency Keys [CRITICAL - C03]
**Priority**: P0 - BLOCK PRODUCTION
**Assignee**: Backend Developer
**ETA**: 2 days

**Day 1: Schema & Middleware**

- [ ] Create idempotency_keys table

```sql
-- migrations/Version20251207000000_CreateIdempotencyKeys.php
CREATE TABLE idempotency_keys (
    key VARCHAR(255) PRIMARY KEY,
    tenant_id UUID NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    request_hash TEXT NOT NULL,
    response_status INT NOT NULL,
    response_body JSONB NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_idempotency_tenant ON idempotency_keys(tenant_id);
CREATE INDEX idx_idempotency_expires ON idempotency_keys(expires_at);
```

- [ ] Create IdempotencyKey value object

```php
// src/Shared/Domain/ValueObject/IdempotencyKey.php
final readonly class IdempotencyKey {
    private const KEY_PATTERN = '/^[a-zA-Z0-9_-]{8,128}$/';

    private function __construct(private string $value) {
        if (!preg_match(self::KEY_PATTERN, $value)) {
            throw new \InvalidArgumentException('Invalid idempotency key format');
        }
    }

    public static function fromString(string $value): self {
        return new self($value);
    }

    public function toString(): string {
        return $this->value;
    }
}
```

- [ ] Create IdempotencyMiddleware

```php
// src/Shared/Infrastructure/Http/Middleware/IdempotencyMiddleware.php
final class IdempotencyMiddleware implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only for POST/PUT/PATCH/DELETE
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return;
        }

        // Only for configured endpoints
        if (!$this->isIdempotentEndpoint($request->getPathInfo())) {
            return;
        }

        $idempotencyKey = $request->headers->get('Idempotency-Key');
        if (!$idempotencyKey) {
            // Required for orders and payments
            if ($this->isStrictIdempotencyRequired($request->getPathInfo())) {
                throw new BadRequestHttpException('Idempotency-Key header required');
            }
            return;
        }

        // Check if key exists
        $cached = $this->repository->findByKey(
            IdempotencyKey::fromString($idempotencyKey),
            $this->getTenantId($request)
        );

        if ($cached && $this->isRequestMatch($request, $cached)) {
            // Return cached response
            $event->setResponse(new JsonResponse(
                json_decode($cached['response_body'], true),
                $cached['response_status']
            ));
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // Store response for future requests
        // TTL: 24 hours
    }
}
```

**Day 2: Integration & Testing**

- [ ] Register middleware in services.yaml
- [ ] Configure idempotent endpoints

```yaml
# config/packages/idempotency.yaml
idempotency:
    enabled: true
    ttl: 86400  # 24 hours
    strict_endpoints:  # Require Idempotency-Key
        - '^/api/v1/orders$'
        - '^/api/orders$'
        - '^/api/v1/payments$'
        - '^/api/payments$'
        - '^/api/v1/payments/stripe/create-intent$'
    optional_endpoints:  # Support but don't require
        - '^/api/v1/customers$'
        - '^/api/customers$'
```

- [ ] Write tests

```php
// tests/Functional/Idempotency/IdempotencyTest.php
// - testIdempotencyKeyRequired()
// - testIdempotencyKeyReturnsCachedResponse()
// - testIdempotencyKeyExpiresAfter24Hours()
// - testDifferentRequestBodyWithSameKey()
// - testIdempotencyAcrossDifferentTenants()
```

- [ ] Test with Postman: Create order twice with same key
- [ ] Document in API documentation with examples

**Verification**:
```bash
# Test idempotency key required
curl -X POST http://localhost:8000/api/v1/orders \
  -H "Content-Type: application/json" \
  -d '{...}'
# Expected: 400 Bad Request - "Idempotency-Key header required"

# Test idempotency works
KEY=$(uuidgen)
curl -X POST http://localhost:8000/api/v1/orders \
  -H "Idempotency-Key: $KEY" \
  -H "Content-Type: application/json" \
  -d '{...}'
# Expected: 201 Created + order details

# Replay request
curl -X POST http://localhost:8000/api/v1/orders \
  -H "Idempotency-Key: $KEY" \
  -H "Content-Type: application/json" \
  -d '{...}'
# Expected: 201 Created + SAME order details (cached response)

# Verify no duplicate order in database
SELECT COUNT(*) FROM orders WHERE id = '...';
# Expected: 1 (not 2)
```

---

### Task 2.3: Implement Encryption at Rest [CRITICAL - C02]
**Priority**: P0 - BLOCK PRODUCTION
**Assignee**: Senior Backend Developer + DBA
**ETA**: 3-5 days

**Approach: Application-Level Encryption (Recommended)**

**Day 1-2: Infrastructure Setup**

- [ ] Choose encryption library

```bash
composer require paragonie/halite  # Libsodium wrapper (recommended)
# OR
# Use PHP's openssl extension (built-in)
```

- [ ] Create encryption key management

```php
// src/Shared/Infrastructure/Encryption/EncryptionService.php
final class EncryptionService
{
    public function __construct(
        private string $encryptionKey  // From Symfony Secrets
    ) {}

    public function encrypt(string $plaintext): string
    {
        // Using Halite (libsodium wrapper)
        $keyPair = KeyFactory::loadEncryptionKey($this->encryptionKey);
        return Crypto::encrypt(
            new HiddenString($plaintext),
            $keyPair
        );
    }

    public function decrypt(string $ciphertext): string
    {
        $keyPair = KeyFactory::loadEncryptionKey($this->encryptionKey);
        return Crypto::decrypt(
            $ciphertext,
            $keyPair
        )->getString();
    }
}
```

- [ ] Generate encryption key

```bash
# Generate 256-bit key
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
# Store in Symfony Secrets
php bin/console secrets:set ENCRYPTION_KEY --env=prod
```

**Day 3-4: Encrypt Sensitive Fields**

- [ ] Create EncryptedString value object

```php
// src/Shared/Domain/ValueObject/EncryptedString.php
final class EncryptedString
{
    private function __construct(
        private string $encrypted
    ) {}

    public static function fromPlain(
        string $plain,
        EncryptionService $encryptor
    ): self {
        return new self($encryptor->encrypt($plain));
    }

    public function toPlain(EncryptionService $encryptor): string
    {
        return $encryptor->decrypt($this->encrypted);
    }

    public function getEncrypted(): string
    {
        return $this->encrypted;
    }
}
```

- [ ] Add encrypted fields to entities

```php
// Example: Customer email encryption
// src/Customer/Infrastructure/Persistence/Doctrine/Entity/CustomerEntity.php

#[ORM\Column(type: 'text', nullable: true)]
private ?string $emailEncrypted = null;

// Doctrine custom type for EncryptedString
#[ORM\Column(type: 'encrypted_string', nullable: true)]
private ?EncryptedString $phoneEncrypted = null;
```

- [ ] Create Doctrine custom type

```php
// src/Shared/Infrastructure/Doctrine/Type/EncryptedStringType.php
final class EncryptedStringType extends Type
{
    public function convertToPHPValue($value, AbstractPlatform $platform): ?EncryptedString
    {
        if ($value === null) {
            return null;
        }
        return new EncryptedString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        return $value->getEncrypted();
    }
}
```

- [ ] Register custom type

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        types:
            encrypted_string: App\Shared\Infrastructure\Doctrine\Type\EncryptedStringType
```

**Day 5: Migration & Testing**

- [ ] Create migration to add encrypted columns

```php
// migrations/Version20251208000000_AddEncryptedFields.php
public function up(Schema $schema): void
{
    // Add encrypted columns
    $this->addSql('ALTER TABLE customers ADD email_encrypted TEXT DEFAULT NULL');
    $this->addSql('ALTER TABLE customers ADD phone_encrypted TEXT DEFAULT NULL');
    $this->addSql('ALTER TABLE customers ADD address_encrypted TEXT DEFAULT NULL');

    // Migrate existing data
    $this->addSql("
        UPDATE customers
        SET email_encrypted = pgp_sym_encrypt(email, current_setting('app.encryption_key'))
        WHERE email IS NOT NULL
    ");
}
```

- [ ] Data migration script

```php
// src/Command/MigrateToEncryptedFieldsCommand.php
// Batch process existing records
// Encrypt sensitive fields
// Verify decryption works
```

- [ ] Write tests

```php
// tests/Unit/Shared/Infrastructure/Encryption/EncryptionServiceTest.php
// - testEncryptDecrypt()
// - testEncryptedValuesAreDifferent()
// - testDecryptionWithWrongKey()
```

- [ ] Performance test: Benchmark encryption/decryption overhead
- [ ] Document encryption strategy in runbook

**Fields to Encrypt**:
- [ ] Customer.email
- [ ] Customer.phone
- [ ] Customer.address (full address object)
- [ ] Payment.gatewayMetadata (if contains sensitive data)
- [ ] User.email (consider: affects login)

**Verification**:
```bash
# Test encryption in database
psql -d ecom -c "SELECT email_encrypted FROM customers LIMIT 1;"
# Expected: Encrypted gibberish, not plaintext

# Test decryption in application
curl http://localhost:8000/api/v1/customers/123 -H "Authorization: Bearer $JWT"
# Expected: {"email": "customer@example.com", ...} (decrypted)

# Test query performance
time psql -d ecom -c "SELECT * FROM customers WHERE email_encrypted IS NOT NULL LIMIT 1000;"
# Expected: < 100ms (acceptable overhead)
```

**Key Rotation Plan** (document, implement later):
1. Generate new encryption key
2. Add `key_version` column to encrypted tables
3. Re-encrypt all data with new key in background job
4. Update `key_version` to 2
5. Retire old key after 30 days

---

## Phase 3: HARDENING (Days 8-10)

### Task 3.1: Implement Password Policy [MEDIUM - M01]
**Priority**: P2
**Assignee**: Backend Developer
**ETA**: 1 day

- [ ] Create PasswordPolicy value object

```php
// src/User/Domain/ValueObject/PasswordPolicy.php
final readonly class PasswordPolicy
{
    private const MIN_LENGTH = 12;
    private const REQUIRE_UPPERCASE = true;
    private const REQUIRE_LOWERCASE = true;
    private const REQUIRE_DIGIT = true;
    private const REQUIRE_SPECIAL = true;

    public static function validate(string $password): void
    {
        if (strlen($password) < self::MIN_LENGTH) {
            throw new \DomainException(sprintf(
                'Password must be at least %d characters',
                self::MIN_LENGTH
            ));
        }

        if (self::REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            throw new \DomainException('Password must contain uppercase letter');
        }

        // ... other validations
    }

    public static function isStrong(string $password): bool
    {
        // Check against common passwords list
        // Check for keyboard patterns (qwerty, 123456)
        // Check for dictionary words
    }
}
```

- [ ] Update User aggregate to validate password policy
- [ ] Add password strength indicator to frontend
- [ ] Test with weak passwords

**Verification**:
```bash
# Test weak password rejected
curl -X POST http://localhost:8000/api/v1/auth/register \
  -d '{"email":"test@example.com","password":"password123"}'
# Expected: 400 Bad Request - "Password must contain special character"

# Test strong password accepted
curl -X POST http://localhost:8000/api/v1/auth/register \
  -d '{"email":"test@example.com","password":"P@ssw0rd!2024"}'
# Expected: 201 Created
```

---

### Task 3.2: Implement Vendor Ownership Checks [MEDIUM - M02]
**Priority**: P2
**Assignee**: Backend Developer
**ETA**: 1 day

- [ ] Add `vendor_id` field to Product aggregate
- [ ] Update ProductVoter to check ownership

```php
// src/Catalog/Infrastructure/Security/ProductVoter.php
protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
{
    // ... existing checks

    // VENDOR: check ownership
    if ($this->hasRole($token, 'ROLE_VENDOR')) {
        if (self::CREATE === $attribute) {
            return true;
        }

        // For VIEW, EDIT, DELETE: check ownership
        if ($subject instanceof ProductEntity) {
            $user = $this->getUser($token);
            return $subject->getVendorId() === $user->getId();
        }

        return false;
    }

    return false;
}
```

- [ ] Write tests for vendor ownership scenarios
- [ ] Update API documentation

**Verification**:
```bash
# Test vendor can create product
curl -X POST http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer $VENDOR_JWT" \
  -d '{...}'
# Expected: 201 Created

# Test vendor cannot edit other vendor's product
curl -X PATCH http://localhost:8000/api/v1/products/other-vendor-product-id \
  -H "Authorization: Bearer $VENDOR_JWT" \
  -d '{...}'
# Expected: 403 Forbidden
```

---

### Task 3.3: Verify Rate Limiter Application [MEDIUM - M03]
**Priority**: P2
**Assignee**: Backend Developer
**ETA**: 0.5 day

- [ ] Audit all API controllers for rate limiter attributes
- [ ] Add missing rate limiters

```php
// Example: OrderController
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[Route('/api/v1/orders', methods: ['POST'])]
public function placeOrder(
    Request $request,
    #[Autowire(service: 'limiter.orders_place')] RateLimiterFactory $limiter
): JsonResponse {
    $limiter = $limiter->create($request->getClientIp());
    if (!$limiter->consume(1)->isAccepted()) {
        throw new TooManyRequestsHttpException(
            'Rate limit exceeded. Try again in 60 seconds.'
        );
    }

    // ... process order
}
```

- [ ] Test rate limiting with artillery/k6
- [ ] Document rate limits in API documentation

**Verification**:
```bash
# Test rate limit enforcement
for i in {1..15}; do
  curl -X POST http://localhost:8000/api/v1/orders \
    -H "Idempotency-Key: key-$i" \
    -d '{...}'
done
# Expected: First 10 succeed (201), next 5 fail (429 Too Many Requests)
```

---

### Task 3.4: Audit Log Coverage [MEDIUM - M04]
**Priority**: P2
**Assignee**: Backend Developer
**ETA**: 1 day

- [ ] List all critical operations that should be logged:
  - [ ] User login/logout
  - [ ] User role changes
  - [ ] Order creation/cancellation/refund
  - [ ] Payment capture/refund
  - [ ] Product creation/update/delete
  - [ ] Customer data access (GDPR)
  - [ ] Settings modifications
  - [ ] Tenant activation/deactivation

- [ ] Verify DomainEventAuditSubscriber covers all events
- [ ] Add missing audit log entries
- [ ] Test audit log completeness

**Verification**:
```bash
# Perform sensitive operation
curl -X DELETE http://localhost:8000/api/v1/products/123 \
  -H "Authorization: Bearer $JWT"

# Check audit log
curl http://localhost:8000/api/v1/audit-logs \
  -H "Authorization: Bearer $ADMIN_JWT" \
  | jq '.[] | select(.resourceType == "product" and .actionType == "delete")'
# Expected: Audit entry found with user_id, ip_address, timestamp
```

---

## Phase 4: TESTING & DEPLOYMENT (Days 11-12)

### Task 4.1: Security Test Suite
**Priority**: P1
**Assignee**: QA Engineer
**ETA**: 1 day

- [ ] Authentication tests
  - [ ] JWT token expiry enforcement
  - [ ] MFA enrollment flow
  - [ ] MFA verification flow
  - [ ] Backup code usage

- [ ] Authorization tests
  - [ ] Role-based access control
  - [ ] Voter permission checks
  - [ ] Cross-tenant access prevention

- [ ] API security tests
  - [ ] Rate limiting enforcement
  - [ ] CORS header validation
  - [ ] Idempotency key handling
  - [ ] Public endpoint restrictions

- [ ] Data security tests
  - [ ] Encryption at rest verification
  - [ ] TLS enforcement
  - [ ] Webhook signature verification

**Tools**:
- PHPUnit (unit/integration tests)
- OWASP ZAP (automated security scan)
- Burp Suite (manual penetration testing)

---

### Task 4.2: Penetration Testing
**Priority**: P1
**Assignee**: External Security Firm
**ETA**: 2-3 days

**Scope**:
- [ ] OWASP Top 10 vulnerabilities
- [ ] Multi-tenancy isolation testing
- [ ] Authentication bypass attempts
- [ ] Privilege escalation attempts
- [ ] SQL injection testing
- [ ] XSS testing
- [ ] CSRF testing
- [ ] API abuse testing

**Deliverables**:
- [ ] Penetration test report
- [ ] Remediation recommendations
- [ ] Re-test after fixes

---

### Task 4.3: Production Deployment Checklist
**Priority**: P0
**Assignee**: DevOps Engineer
**ETA**: 0.5 day

- [ ] All P0 findings resolved (C01-C05)
- [ ] All P1 findings resolved (H01-H03)
- [ ] Security test suite passes (100%)
- [ ] Penetration test report reviewed and remediated
- [ ] Secrets rotated after moving to vault
- [ ] Environment-specific configs deployed (dev/staging/prod)
- [ ] TLS 1.3 enforced on production servers
- [ ] HSTS enabled in production
- [ ] CORS allowlist configured for production domains
- [ ] Rate limiters verified on all endpoints
- [ ] Monitoring and alerting configured
- [ ] Incident response plan documented
- [ ] Security runbook updated

---

## Verification & Sign-Off

### Security Team Sign-Off
- [ ] All CRITICAL findings resolved
- [ ] All HIGH findings resolved
- [ ] Penetration test passed
- [ ] Security test suite passing
- [ ] Production deployment checklist complete

**Signed**: ___________________ Date: ___________

### Product Owner Sign-Off
- [ ] All PRD security requirements met
- [ ] Compliance requirements satisfied (GDPR, PCI DSS, SOC 2)
- [ ] Risk assessment acceptable

**Signed**: ___________________ Date: ___________

### DevOps Sign-Off
- [ ] Production environment secured
- [ ] Secrets management configured
- [ ] Monitoring and alerting operational
- [ ] Incident response plan ready

**Signed**: ___________________ Date: ___________

---

## Post-Deployment Monitoring

### Week 1: Intensive Monitoring
- [ ] Daily security dashboard review
- [ ] Monitor failed login attempts
- [ ] Monitor rate limiter hits
- [ ] Monitor audit log for anomalies
- [ ] Check for unusual API patterns

### Week 2-4: Standard Monitoring
- [ ] Weekly security dashboard review
- [ ] Weekly vulnerability scan
- [ ] Review audit logs for sensitive operations
- [ ] Check for new CVEs in dependencies

### Monthly: Security Review
- [ ] Review access logs
- [ ] Review audit logs
- [ ] Dependency vulnerability scan
- [ ] Update security documentation

---

## Resources

### Documentation
- Full Audit: `SECURITY_AUDIT_REPORT_2025-12-05.md`
- Executive Summary: `SECURITY_AUDIT_EXECUTIVE_SUMMARY.md`
- PRD Security Requirements: `docs/business/ECOM_PRD_v5.1.md` Section 8

### Tools
- Symfony Secrets: https://symfony.com/doc/current/configuration/secrets.html
- scheb/2fa-bundle: https://github.com/scheb/2fa
- Halite (libsodium): https://github.com/paragonie/halite
- OWASP ZAP: https://www.zaproxy.org/

### Training
- OWASP Top 10: https://owasp.org/www-project-top-ten/
- Symfony Security: https://symfony.com/doc/current/security.html
- PCI DSS Compliance: https://www.pcisecuritystandards.org/

---

**Last Updated**: 2025-12-05
**Next Review**: 2025-12-15 (after Phase 2 completion)
