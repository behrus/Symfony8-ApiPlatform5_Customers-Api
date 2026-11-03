<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103112304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Soft delete for customers: add deleted_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer ADD deleted_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer DROP deleted_at');
    }
}
