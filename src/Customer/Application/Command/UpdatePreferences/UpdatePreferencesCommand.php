<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\UpdatePreferences;

/**
 * Update Customer Preferences Command.
 *
 * Command to update customer preferences (language, currency, notifications).
 */
final readonly class UpdatePreferencesCommand
{
    /**
     * @param array<string, mixed> $preferences
     */
    public function __construct(
        public string $customerId,
        public string $tenantId,
        public array $preferences,
    ) {
    }
}
