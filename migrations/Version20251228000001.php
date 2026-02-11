<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add customer segment pricing support to Price Lists and Promotions.
 *
 * - Adds segment_rules JSON column to price_lists table
 * - Adds target_segments JSON column to promotions table
 */
final class Version20251228000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer segment pricing support to Price Lists and Promotions';
    }

    public function up(Schema $schema): void
    {
        // Add segment_rules to price_lists table
        $this->addSql('ALTER TABLE price_lists ADD COLUMN segment_rules JSON NOT NULL DEFAULT \'[]\'');

        // Add target_segments to promotions table
        $this->addSql('ALTER TABLE promotions ADD COLUMN target_segments JSON NOT NULL DEFAULT \'[]\'');

        // Add comment for documentation
        $this->addSql('COMMENT ON COLUMN price_lists.segment_rules IS \'Segment-based pricing rules (higher priority than general rules)\'');
        $this->addSql('COMMENT ON COLUMN promotions.target_segments IS \'Customer segments this promotion applies to (empty = all segments)\'');
    }

    public function down(Schema $schema): void
    {
        // Remove segment_rules from price_lists
        $this->addSql('ALTER TABLE price_lists DROP COLUMN segment_rules');

        // Remove target_segments from promotions
        $this->addSql('ALTER TABLE promotions DROP COLUMN target_segments');
    }
}
