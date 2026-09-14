<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914084401 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add manager database assignments and per-table CRUD permission overrides';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_database_access (id BINARY(16) NOT NULL, mode VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id BINARY(16) NOT NULL, connection_id BINARY(16) NOT NULL, UNIQUE INDEX uniq_user_database_access (user_id, connection_id), INDEX IDX_D0AB1513A76ED395 (user_id), INDEX IDX_D0AB1513DD03F01 (connection_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_table_permissions (id BINARY(16) NOT NULL, table_name VARCHAR(64) NOT NULL, select_allowed TINYINT DEFAULT NULL, insert_allowed TINYINT DEFAULT NULL, update_allowed TINYINT DEFAULT NULL, delete_allowed TINYINT DEFAULT NULL, updated_at DATETIME NOT NULL, access_id BINARY(16) NOT NULL, UNIQUE INDEX uniq_access_table_permission (access_id, table_name), INDEX IDX_E6AC84F94FEA67CF (access_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_database_access ADD CONSTRAINT FK_D0AB1513A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_database_access ADD CONSTRAINT FK_D0AB1513DD03F01 FOREIGN KEY (connection_id) REFERENCES client_connections (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_table_permissions ADD CONSTRAINT FK_E6AC84F94FEA67CF FOREIGN KEY (access_id) REFERENCES user_database_access (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_database_access DROP FOREIGN KEY FK_D0AB1513A76ED395');
        $this->addSql('ALTER TABLE user_database_access DROP FOREIGN KEY FK_D0AB1513DD03F01');
        $this->addSql('ALTER TABLE user_table_permissions DROP FOREIGN KEY FK_E6AC84F94FEA67CF');
        $this->addSql('DROP TABLE user_database_access');
        $this->addSql('DROP TABLE user_table_permissions');
    }
}
