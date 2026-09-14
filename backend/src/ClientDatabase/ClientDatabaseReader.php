<?php

namespace App\ClientDatabase;

use App\Connection\ClientDatabaseCredentials;
use App\Dto\BrowseRowsQuery;

interface ClientDatabaseReader
{
    /** @return list<SchemaTable> */
    public function schema(ClientDatabaseCredentials $credentials): array;

    public function rows(ClientDatabaseCredentials $credentials, string $tableName, BrowseRowsQuery $query): RowPage;
}
