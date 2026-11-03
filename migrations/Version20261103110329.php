<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103110329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Foreign keys NOT NULL, unique customer email (length 255 as in the entity)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address CHANGE customer_id customer_id INT NOT NULL, CHANGE city_id city_id INT NOT NULL');
        $this->addSql('ALTER TABLE api_token CHANGE user_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE city CHANGE country_id country_id INT NOT NULL');
        $this->addSql('ALTER TABLE customer CHANGE email email VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_81398E09E7927C74 ON customer (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address CHANGE customer_id customer_id INT DEFAULT NULL, CHANGE city_id city_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE api_token CHANGE user_id user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE city CHANGE country_id country_id INT DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_81398E09E7927C74 ON customer');
        $this->addSql('ALTER TABLE customer CHANGE email email VARCHAR(180) NOT NULL');
    }
}
