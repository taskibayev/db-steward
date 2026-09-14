<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914105631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add append-only audit operations and typed row snapshots';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_operations (id BINARY(16) NOT NULL, table_name VARCHAR(64) NOT NULL, action VARCHAR(255) NOT NULL, primary_key JSON NOT NULL, status VARCHAR(255) NOT NULL, affected_rows INT NOT NULL, correlation_id VARCHAR(64) NOT NULL, error_code VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL, actor_id BINARY(16) NOT NULL, connection_id BINARY(16) NOT NULL, INDEX idx_audit_connection_created (connection_id, created_at), INDEX idx_audit_actor_created (actor_id, created_at), INDEX IDX_55CA4BB710DAF24A (actor_id), INDEX IDX_55CA4BB7DD03F01 (connection_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE audit_snapshots (id BINARY(16) NOT NULL, before_data JSON DEFAULT NULL, after_data JSON DEFAULT NULL, diff JSON NOT NULL, operation_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_941708B244AC3583 (operation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE audit_operations ADD CONSTRAINT FK_55CA4BB710DAF24A FOREIGN KEY (actor_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE audit_operations ADD CONSTRAINT FK_55CA4BB7DD03F01 FOREIGN KEY (connection_id) REFERENCES client_connections (id)');
        $this->addSql('ALTER TABLE audit_snapshots ADD CONSTRAINT FK_941708B244AC3583 FOREIGN KEY (operation_id) REFERENCES audit_operations (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_operations DROP FOREIGN KEY FK_55CA4BB710DAF24A');
        $this->addSql('ALTER TABLE audit_operations DROP FOREIGN KEY FK_55CA4BB7DD03F01');
        $this->addSql('ALTER TABLE audit_snapshots DROP FOREIGN KEY FK_941708B244AC3583');
        $this->addSql('DROP TABLE audit_operations');
        $this->addSql('DROP TABLE audit_snapshots');
    }
}
