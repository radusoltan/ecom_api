<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Enable bypass-finals so PHPUnit can mock/stub final classes in unit tests.
// Must be called after autoload registration but before any final class is loaded.
// Only bypass finals in src/ (production code) - not in tests/ or vendor/.
DG\BypassFinals::enable();
DG\BypassFinals::setWhitelist([
    '*/vendor/elasticsearch/elasticsearch/src/Client.php',
    '*/vendor/elasticsearch/elasticsearch/src/Response/Elasticsearch.php',
    '*/src/Shared/Infrastructure/Elasticsearch/*',
    '*/src/Catalog/Infrastructure/Analytics/*',
    '*/src/Search/Infrastructure/Elasticsearch/*',
    '*/src/Payment/Infrastructure/Gateway/*',
    '*/src/Payment/Application/Service/*',
    '*/src/Payment/Domain/Service/*',
    '*/src/Payment/Domain/Model/*',
    '*/src/Notifications/Domain/Service/*',
    '*/src/Catalog/Domain/Model/*',
    '*/src/Catalog/Domain/ValueObject/*',
    '*/src/Catalog/Domain/Service/*',
    '*/src/Catalog/Application/*',
    '*/src/Order/Domain/Service/*',
    '*/src/Order/Domain/Model/*',
    '*/src/Order/Application/Service/*',
    '*/src/Inventory/Domain/Model/*',
    '*/src/Inventory/Application/*',
    '*/src/Tax/Domain/Service/*',
    '*/src/Tax/Domain/Model/*',
    '*/src/Tax/Application/*',
    '*/src/Pricing/Application/*',
    '*/src/Pricing/Domain/Model/*',
    '*/src/Pricing/Domain/ValueObject/*',
    '*/src/Pricing/Domain/Service/*',
    '*/src/Customer/Application/*',
    '*/src/Customer/Domain/Model/*',
    '*/src/Customer/Domain/Service/*',
    '*/src/Shared/Infrastructure/Encryption/BlindIndexService.php',
    '*/src/Shared/Infrastructure/Encryption/EncryptionService.php',
    '*/src/Shared/Infrastructure/Cache/*',
    '*/src/Shared/Domain/*',
    '*/src/Cart/Application/Service/*',
    '*/src/Cart/Domain/Model/*',
    '*/src/Invoice/Application/Query/*',
    '*/src/Invoice/Application/Command/*',
    '*/src/Invoice/Domain/Model/*',
    '*/src/Invoice/Domain/Service/*',
    '*/src/Invoice/Infrastructure/NumberGenerator/*',
    '*/src/Monitoring/Infrastructure/Service/*',
    '*/src/Catalog/Infrastructure/Cache/*',
    '*/src/Catalog/Infrastructure/Elasticsearch/*',
    '*/src/Catalog/Infrastructure/ApiPlatform/State/*',
    '*/src/Catalog/Presentation/Api/State/*',
    '*/src/Shared/Infrastructure/Tenant/TenantContext.php',
    '*/src/Tenant/Domain/Model/*',
    '*/src/Tenant/Application/*',
    '*/src/User/Domain/Model/*',
    '*/src/User/Application/*',
    '*/src/Internationalization/Infrastructure/Cache/*',
    '*/src/Internationalization/Infrastructure/Parser/*',
    '*/src/Internationalization/Domain/*',
    '*/src/Internationalization/Application/*',
    '*/src/Privacy/Domain/*',
    '*/src/Returns/Domain/*',
    '*/src/Search/Domain/*',
    '*/src/Review/Domain/*',
    '*/src/Wishlist/Domain/*',
    '*/src/Media/Domain/*',
    '*/src/Shipping/Domain/*',
    '*/src/Shipping/Application/*',
    '*/src/Monitoring/Application/*',
    '*/src/Notifications/Application/*',
    '*/src/AuditLog/Application/*',
    '*/src/Order/Application/Command/*',
    '*/src/Order/Application/Query/*',
    '*/src/Order/Application/DTO/*',
    '*/src/Payment/Application/Command/*',
    '*/src/Payment/Application/Query/*',
    '*/src/Payment/Application/DTO/*',
    '*/src/Customer/Presentation/*',
    '*/src/Pricing/Presentation/*',
    '*/src/User/Domain/Event/*',
    '*/src/Order/Domain/Event/*',
    '*/src/Review/Domain/Event/*',
    '*/src/Tax/Infrastructure/Persistence/Doctrine/Entity/*',
    '*/src/Order/Infrastructure/Persistence/Doctrine/Entity/*',
    '*/src/Invoice/Infrastructure/Persistence/Doctrine/Entity/*',
    '*/src/Inventory/Infrastructure/Persistence/Doctrine/Entity/*',
    '*/src/Payment/Infrastructure/Persistence/Doctrine/Entity/*',
    '*/src/Customer/Infrastructure/Persistence/Doctrine/Entity/*',
    '*/src/Customer/Infrastructure/Persistence/Doctrine/Repository/*',
    '*/src/Review/Presentation/*',
    '*/src/Wishlist/Presentation/*',
    '*/src/Order/Infrastructure/ApiPlatform/*',
    '*/src/Inventory/Presentation/*',
    '*/src/Catalog/Presentation/*',
    '*/src/Payment/Domain/ValueObject/*',
    '*/src/Order/Domain/ValueObject/*',
    '*/src/Customer/Domain/ValueObject/*',
    '*/src/Inventory/Domain/ValueObject/*',
    '*/src/Invoice/Domain/ValueObject/*',
    '*/vendor/brick/money/src/*',
    '*/vendor/doctrine/orm/src/Query.php',
    '*/src/Tax/Domain/ValueObject/*',
]);

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Set default tenant ID for tests to avoid RLS violations
// This UUID is used by all tests that need tenant context
if ('test' === $_SERVER['APP_ENV']) {
    // Use a fixed UUID v4 for the default test tenant
    // Format: xxxxxxxx-xxxx-4xxx-8xxx-xxxxxxxxxxxx (v4 with variant bits)
    $_ENV['DEFAULT_TENANT_ID'] = '00000000-0000-4000-8000-000000000001';

    // Optionally load test-specific environment overrides
    if (file_exists(dirname(__DIR__).'/.env.test.local')) {
        (new Dotenv())->load(dirname(__DIR__).'/.env.test.local');
    }
}
