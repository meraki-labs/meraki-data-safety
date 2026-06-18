<?php

/**
 * @internal
 * Managed by Meraki Core Team
 */

namespace Meraki\Packages\DataSafety\Helpers;

class FileGenerateHelper
{
    /**
     * Function: Generate backup table file name
     * @param string $table
     * @param string $version
     * @return string
     */
    public static function table(string $table, string $version): string
    {
        return "meraki_data_safer_{$table}_{$version}.json";
    }

    /**
     * Function: Generate backup columns file name
     * @param string $table
     * @param array $columns
     * @param string $version
     * @return string
     */
    public static function columns(string $table, array $columns, string $version): string
    {
        $cols = implode('_', $columns);
        return "meraki_data_safer_{$table}_{$cols}_{$version}.json";
    }
}
