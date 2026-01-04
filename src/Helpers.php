<?php

namespace Meraki\DataSafety;

class Helpers
{
    public static function makeSnapshotFileName(string $table, array $keyColumns = null, array $columns = null, string $version = null): string
    {
        $keyColumnsPart = $keyColumns ? "_" . implode("_", $keyColumns) : '';
        $columnPart = $columns ? "_" . implode("_", $columns) : '_all';
        $versionPart = $version ? "_$version" : '';
        return "{$table}{$keyColumnsPart}{$columnPart}{$versionPart}.json";
    }

    public static function ensureStoragePath(string $path): void
    {
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
    }
}
