<?php

namespace App\Crud;

use App\Connection\ClientDatabaseCredentials;

interface ClientRowWriter
{
    /** @param array<string, mixed> $values */
    public function insert(ClientDatabaseCredentials $credentials, string $table, array $values): RowMutationResult;

    /**
     * @param array<string, mixed> $primaryKey
     * @param array<string, mixed> $values
     */
    public function update(ClientDatabaseCredentials $credentials, string $table, array $primaryKey, array $values): RowMutationResult;

    /** @param array<string, mixed> $primaryKey */
    public function delete(ClientDatabaseCredentials $credentials, string $table, array $primaryKey): RowMutationResult;

    /**
     * @param array<string, mixed>                $primaryKey
     * @param array<string, array<string, mixed>> $expectedAfter
     */
    public function undoInsert(ClientDatabaseCredentials $credentials, string $table, array $primaryKey, array $expectedAfter): RowMutationResult;

    /**
     * @param array<string, mixed>                $primaryKey
     * @param array<string, array<string, mixed>> $before
     * @param array<string, array<string, mixed>> $expectedAfter
     */
    public function undoUpdate(ClientDatabaseCredentials $credentials, string $table, array $primaryKey, array $before, array $expectedAfter): RowMutationResult;

    /**
     * @param array<string, mixed>                $primaryKey
     * @param array<string, array<string, mixed>> $before
     */
    public function undoDelete(ClientDatabaseCredentials $credentials, string $table, array $primaryKey, array $before): RowMutationResult;
}
