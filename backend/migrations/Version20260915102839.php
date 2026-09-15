<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260915102839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persistent per-user notifications with unread state.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notifications (id BINARY(16) NOT NULL, type VARCHAR(64) NOT NULL, data JSON NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, recipient_id BINARY(16) NOT NULL, INDEX idx_notifications_recipient_created (recipient_id, created_at), INDEX idx_notifications_recipient_read (recipient_id, read_at), INDEX IDX_6000B0D3E92F8F78 (recipient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3E92F8F78 FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3E92F8F78');
        $this->addSql('DROP TABLE notifications');
    }
}
