<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915090443 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Use non-reserved names for temporary result JSON columns';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE temporary_query_results RENAME COLUMN columns TO column_names, RENAME COLUMN `rows` TO result_rows');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE temporary_query_results RENAME COLUMN column_names TO columns, RENAME COLUMN result_rows TO `rows`');
    }
}
