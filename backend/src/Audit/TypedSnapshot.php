<?php

namespace App\Audit;

use App\ClientDatabase\SchemaTable;

final class TypedSnapshot
{
    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, array<string, mixed>>
     */
    public function encode(SchemaTable $table, array $row): array
    {
        $result = [];
        foreach ($table->columns as $column) {
            $value = $row[$column->name] ?? null;
            if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                $value = ['encoding' => 'base64', 'value' => base64_encode($value)];
            }
            $result[$column->name] = ['type' => $column->type, 'value' => $value];
        }

        return $result;
    }

    /**
     * @param array<string, array<string, mixed>>|null $before
     * @param array<string, array<string, mixed>>|null $after
     *
     * @return array<string, array{before: array<string, mixed>|null, after: array<string, mixed>|null}>
     */
    public function diff(?array $before, ?array $after): array
    {
        $result = [];
        foreach (array_unique(array_merge(array_keys($before ?? []), array_keys($after ?? []))) as $column) {
            $old = $before[$column] ?? null;
            $new = $after[$column] ?? null;
            if ($old !== $new) {
                $result[$column] = ['before' => $old, 'after' => $new];
            }
        }

        return $result;
    }
}
