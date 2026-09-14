<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260913202335 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Create encrypted client database connections';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client_connections (id BINARY(16) NOT NULL, name VARCHAR(100) NOT NULL, host VARCHAR(255) NOT NULL, port INT NOT NULL, database_name VARCHAR(64) NOT NULL, encrypted_username LONGTEXT NOT NULL, encrypted_password LONGTEXT NOT NULL, active TINYINT NOT NULL, status VARCHAR(255) NOT NULL, server_version VARCHAR(255) DEFAULT NULL, last_error_code VARCHAR(64) DEFAULT NULL, last_checked_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_client_connections_name (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE client_connections');
    }
}
