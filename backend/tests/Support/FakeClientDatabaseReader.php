<?php

namespace App\Tests\Support;

use App\ClientDatabase\ClientDatabaseReader;
use App\ClientDatabase\ClientDatabaseReadFailed;
use App\ClientDatabase\RowPage;
use App\ClientDatabase\SchemaColumn;
use App\ClientDatabase\SchemaTable;
use App\ClientDatabase\UnknownSchemaIdentifier;
use App\Connection\ClientDatabaseCredentials;
use App\Dto\BrowseRowsQuery;

final class FakeClientDatabaseReader implements ClientDatabaseReader
{
    /** @var list<SchemaTable> */
    public array $tables;
    public bool $fail = false;

    public function __construct()
    {
        $this->tables = [
            new SchemaTable('orders', 'table', [
                new SchemaColumn('id', 'int', false, true, false),
                new SchemaColumn('customer', 'varchar(100)', false, false, false),
            ], ['id']),
            new SchemaTable('report', 'view', [
                new SchemaColumn('total', 'decimal(10,2)', true, false, false),
            ], []),
        ];
    }

    public function schema(ClientDatabaseCredentials $credentials): array
    {
        if ($this->fail) {
            throw new ClientDatabaseReadFailed();
        }

        return $this->tables;
    }

    public function rows(ClientDatabaseCredentials $credentials, string $tableName, BrowseRowsQuery $query): RowPage
    {
        foreach ($this->tables as $table) {
            if ($table->name === $tableName) {
                return new RowPage($table, [['id' => 1, 'customer' => 'Acme']], $query->page, $query->pageSize, 1);
            }
        }

        throw new UnknownSchemaIdentifier();
    }
}
