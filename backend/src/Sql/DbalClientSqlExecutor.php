<?php

namespace App\Sql;

use App\ClientDatabase\DbalClientConnectionFactory;
use App\Connection\ClientDatabaseCredentials;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ClientSqlExecutor::class)]
final class DbalClientSqlExecutor implements ClientSqlExecutor
{
    public function __construct(private readonly DbalClientConnectionFactory $connections)
    {
    }

    public function execute(ClientDatabaseCredentials $credentials, ParsedSql $sql): SqlQueryResult
    {
        $connection = null;
        try {
            $connection = $this->connections->create($credentials);
            if (SqlOperation::Select === $sql->operation) {
                $query = 'SELECT * FROM ('.$sql->sql.') AS db_steward_result LIMIT 1001';
                $rows = $connection->executeQuery($query)->fetchAllAssociative();
                $truncated = 1000 < count($rows);
                if ($truncated) {
                    array_pop($rows);
                }
                $rows = array_map($this->safeRow(...), $rows);

                return new SqlQueryResult(array_keys($rows[0] ?? []), $rows, $truncated, null);
            }

            return new SqlQueryResult([], [], false, (int) $connection->executeStatement($sql->sql));
        } catch (\Throwable $exception) {
            throw new ClientSqlFailed($exception);
        } finally {
            $connection?->close();
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function safeRow(array $row): array
    {
        foreach ($row as $column => $value) {
            if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                $row[$column] = ['encoding' => 'base64', 'value' => base64_encode($value)];
            }
        }

        return $row;
    }
}
