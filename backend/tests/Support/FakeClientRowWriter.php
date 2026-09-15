<?php

namespace App\Tests\Support;

use App\ClientDatabase\SchemaColumn;
use App\ClientDatabase\SchemaTable;
use App\Connection\ClientDatabaseCredentials;
use App\Crud\ClientRowWriter;
use App\Crud\RowMutationRejected;
use App\Crud\RowMutationResult;

final class FakeClientRowWriter implements ClientRowWriter
{
    public ?string $rejection = null;

    public function insert(ClientDatabaseCredentials $credentials, string $table, array $values): RowMutationResult
    {
        $this->maybeReject();
        $after = ['id' => 42, ...$values];

        return new RowMutationResult($this->table(), ['id' => 42], null, $after, 1);
    }

    /**
     * @param array<string, mixed> $primaryKey
     * @param array<string, mixed> $values
     */
    public function update(ClientDatabaseCredentials $credentials, string $table, array $primaryKey, array $values): RowMutationResult
    {
        $this->maybeReject();
        $before = ['id' => $primaryKey['id'], 'name' => 'Before'];

        return new RowMutationResult($this->table(), $primaryKey, $before, [...$before, ...$values], 1);
    }

    public function delete(ClientDatabaseCredentials $credentials, string $table, array $primaryKey): RowMutationResult
    {
        $this->maybeReject();

        return new RowMutationResult($this->table(), $primaryKey, ['id' => $primaryKey['id'], 'name' => 'Before'], null, 1);
    }

    /**
     * @param array<string, mixed>                $primaryKey
     * @param array<string, array<string, mixed>> $expectedAfter
     */
    public function undoInsert(ClientDatabaseCredentials $credentials, string $table, array $primaryKey, array $expectedAfter): RowMutationResult
    {
        $this->maybeReject();

        return new RowMutationResult($this->table(), $primaryKey, ['id' => 42, 'name' => 'After'], null, 1);
    }

    /**
     * @param array<string, mixed>                $primaryKey
     * @param array<string, array<string, mixed>> $before
     * @param array<string, array<string, mixed>> $expectedAfter
     */
    public function undoUpdate(ClientDatabaseCredentials $credentials, string $table, array $primaryKey, array $before, array $expectedAfter): RowMutationResult
    {
        $this->maybeReject();

        return new RowMutationResult($this->table(), $primaryKey, ['id' => 42, 'name' => 'After'], ['id' => 42, 'name' => 'Before'], 1);
    }

    /**
     * @param array<string, mixed>                $primaryKey
     * @param array<string, array<string, mixed>> $before
     */
    public function undoDelete(ClientDatabaseCredentials $credentials, string $table, array $primaryKey, array $before): RowMutationResult
    {
        $this->maybeReject();

        return new RowMutationResult($this->table(), $primaryKey, null, ['id' => 42, 'name' => 'Before'], 1);
    }

    private function maybeReject(): void
    {
        if (null !== $this->rejection) {
            throw new RowMutationRejected($this->rejection);
        }
    }

    private function table(): SchemaTable
    {
        return new SchemaTable('customers', 'table', [
            new SchemaColumn('id', 'int', false, true, false, false),
            new SchemaColumn('name', 'varchar(100)', false, false, false, false),
        ], ['id']);
    }
}
