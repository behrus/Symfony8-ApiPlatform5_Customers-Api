<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The API handles only Germany: every city gets this country automatically (GermanCityProcessor).
 */
final class Version20261104210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the country "Germany" if it does not exist yet';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO country (name, created_at, updated_at) SELECT 'Germany', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM country WHERE name = 'Germany')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM country WHERE name = 'Germany' AND id NOT IN (SELECT country_id FROM city WHERE country_id IS NOT NULL)");
    }
}
