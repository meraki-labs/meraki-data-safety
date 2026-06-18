<?php

namespace Meraki\Packages\DataSafety\Tests\Unit;

use Meraki\Packages\DataSafety\Helpers\FileGenerateHelper;
use PHPUnit\Framework\TestCase;

class FileGenerateHelperTest extends TestCase
{
    public function test_table_returns_correct_format(): void
    {
        $result = FileGenerateHelper::table('users', 'v1');
        $this->assertSame('meraki_data_safer_users_v1.json', $result);
    }

    public function test_table_includes_table_name_and_version(): void
    {
        $result = FileGenerateHelper::table('orders', '2026_06_18');
        $this->assertSame('meraki_data_safer_orders_2026_06_18.json', $result);
    }

    public function test_table_ends_with_json_extension(): void
    {
        $result = FileGenerateHelper::table('products', 'v2');
        $this->assertStringEndsWith('.json', $result);
    }

    public function test_columns_returns_correct_format_with_single_column(): void
    {
        $result = FileGenerateHelper::columns('users', ['email'], 'v1');
        $this->assertSame('meraki_data_safer_users_email_v1.json', $result);
    }

    public function test_columns_returns_correct_format_with_multiple_columns(): void
    {
        $result = FileGenerateHelper::columns('users', ['first_name', 'last_name'], 'v1');
        $this->assertSame('meraki_data_safer_users_first_name_last_name_v1.json', $result);
    }

    public function test_columns_joins_column_names_with_underscore(): void
    {
        $result = FileGenerateHelper::columns('products', ['col2', 'col3'], '2026_06_18');
        $this->assertSame('meraki_data_safer_products_col2_col3_2026_06_18.json', $result);
    }

    public function test_columns_ends_with_json_extension(): void
    {
        $result = FileGenerateHelper::columns('orders', ['status', 'amount'], 'v3');
        $this->assertStringEndsWith('.json', $result);
    }

    public function test_table_and_columns_produce_different_filenames_for_same_table(): void
    {
        $tableFile   = FileGenerateHelper::table('users', 'v1');
        $columnsFile = FileGenerateHelper::columns('users', ['email'], 'v1');
        $this->assertNotSame($tableFile, $columnsFile);
    }
}
