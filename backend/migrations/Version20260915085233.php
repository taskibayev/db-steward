<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915085233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add queued SQL jobs, executions, and one-hour query results';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE jobs (
              id BINARY(16) NOT NULL,
              status VARCHAR(255) NOT NULL,
              correlation_id VARCHAR(64) NOT NULL,
              error_code VARCHAR(64) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              started_at DATETIME DEFAULT NULL,
              completed_at DATETIME DEFAULT NULL,
              actor_id BINARY(16) NOT NULL,
              connection_id BINARY(16) NOT NULL,
              INDEX idx_jobs_actor_created (actor_id, created_at),
              INDEX idx_jobs_connection_created (connection_id, created_at),
              INDEX IDX_A8936DC510DAF24A (actor_id),
              INDEX IDX_A8936DC5DD03F01 (connection_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE sql_executions (
              id BINARY(16) NOT NULL,
              sql_text LONGTEXT NOT NULL,
              operation VARCHAR(255) NOT NULL,
              tables JSON NOT NULL,
              affected_rows INT DEFAULT NULL,
              job_id BINARY(16) NOT NULL,
              UNIQUE INDEX UNIQ_D8439C2BBE04EA9 (job_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE temporary_query_results (
              id BINARY(16) NOT NULL,
              columns JSON NOT NULL,
              `rows` JSON NOT NULL,
              row_count INT NOT NULL,
              truncated TINYINT NOT NULL,
              expires_at DATETIME NOT NULL,
              job_id BINARY(16) NOT NULL,
              UNIQUE INDEX UNIQ_EA2535A7BE04EA9 (job_id),
              INDEX idx_query_results_expires (expires_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              jobs
            ADD
              CONSTRAINT FK_A8936DC510DAF24A FOREIGN KEY (actor_id) REFERENCES users (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              jobs
            ADD
              CONSTRAINT FK_A8936DC5DD03F01 FOREIGN KEY (connection_id) REFERENCES client_connections (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sql_executions
            ADD
              CONSTRAINT FK_D8439C2BBE04EA9 FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              temporary_query_results
            ADD
              CONSTRAINT FK_EA2535A7BE04EA9 FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE jobs DROP FOREIGN KEY FK_A8936DC510DAF24A');
        $this->addSql('ALTER TABLE jobs DROP FOREIGN KEY FK_A8936DC5DD03F01');
        $this->addSql('ALTER TABLE sql_executions DROP FOREIGN KEY FK_D8439C2BBE04EA9');
        $this->addSql('ALTER TABLE temporary_query_results DROP FOREIGN KEY FK_EA2535A7BE04EA9');
        $this->addSql('DROP TABLE jobs');
        $this->addSql('DROP TABLE sql_executions');
        $this->addSql('DROP TABLE temporary_query_results');
    }
}
