<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link compensating audit operations to the original operation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_operations ADD undoes_operation_id BINARY(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_operations ADD CONSTRAINT FK_AUDIT_UNDOES FOREIGN KEY (undoes_operation_id) REFERENCES audit_operations (id)');
        $this->addSql('CREATE INDEX idx_audit_undoes_status ON audit_operations (undoes_operation_id, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_operations DROP FOREIGN KEY FK_AUDIT_UNDOES');
        $this->addSql('DROP INDEX idx_audit_undoes_status ON audit_operations');
        $this->addSql('ALTER TABLE audit_operations DROP undoes_operation_id');
    }
}
