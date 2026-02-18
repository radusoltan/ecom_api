<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Enable bypass-finals so PHPUnit can mock/stub final classes in unit tests.
// Must be called after autoload registration but before any final class is loaded.
// Only bypass finals in src/ (production code) - not in tests/ or vendor/.
DG\BypassFinals::enable();
DG\BypassFinals::setWhitelist([
    '*/src/Payment/Infrastructure/Gateway/*',
    '*/src/Notifications/Domain/Service/*',
    '*/src/Order/Domain/Service/*',
    '*/src/Order/Domain/Model/*',
    '*/src/Inventory/Domain/Model/*',
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
