<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stores the SHA-256 hash of an API token instead of the token itself.
 */
final class Version20261104100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace the plain text api_token.token with api_token.token_hash (SHA-256)';
    }

    public function up(Schema $schema): void
    {
        // The old plain text tokens are 120 characters long: hash them first, then shorten the column.
        $this->addSql('ALTER TABLE api_token CHANGE token token_hash VARCHAR(255) NOT NULL');
        $this->addSql('UPDATE api_token SET token_hash = SHA2(token_hash, 256)');
        $this->addSql('ALTER TABLE api_token MODIFY token_hash VARCHAR(64) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7BA2F5EBB3BC57DA ON api_token (token_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The plain text tokens cannot be restored from their hashes.');
    }
}
