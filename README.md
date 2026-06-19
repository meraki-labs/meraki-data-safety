## Meraki Data Safety

### Purpose
Meraki Data Safety helps you safely manage data across Laravel migrations by providing backup, restore, and cleanup operations.

### Installation
```commandline
composer require merakilab/meraki-data-safety
```

### Configuration

Publish the config file:
```commandline
php artisan vendor:publish --tag=meraki-data-safety-config
```

Environment variables:
```dotenv
MERAKI_DATA_SAFETY_ENABLED=true
MERAKI_DATA_SAFETY_DISK=local
MERAKI_DATA_SAFETY_PATH=meraki/data-safety
MERAKI_DATA_SAFETY_BACKUP_CHUNK=1000
MERAKI_DATA_SAFETY_RESTORE_CHUNK=500
```

### Backup / restore a full table before migration down
```php
use Meraki\Packages\DataSafety\Traits\MigrationDataSafety;

return new class extends Migration {
    use MigrationDataSafety;

    const BACKUP_VERSION = '2026_01_03';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('table_name', function (Blueprint $table) {
            $table->id();
            $table->string('col1');
            $table->timestamps();
        });

        // Sync data backup to the table (if exists)
        $this->restoreTable('table_name', ['id'], self::BACKUP_VERSION);

        // Clean up backup file after successful restore
        $this->cleanupTable('table_name', self::BACKUP_VERSION);

        // With pgsql you need to update the index for autoincrement column
        DB::statement("SELECT setval('table_name_id_seq', (SELECT MAX(id) FROM table_name))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backup before drop table
        $this->backupTable('table_name', ['id'], self::BACKUP_VERSION);

        Schema::dropIfExists('table_name');
    }
};
```

### Backup / restore data from specific columns
```php
use Meraki\Packages\DataSafety\Traits\MigrationDataSafety;

return new class extends Migration {
    use MigrationDataSafety;

    const BACKUP_VERSION = '2026_01_04_1754';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            if (!Schema::hasColumns('table_name', ['col2', 'col3'])) {
                Schema::table('table_name', function (Blueprint $table) {
                    $table->enum('col2', ['val1', 'val2'])->default("val1");
                    $table->string('col3')->nullable();
                });
                $this->restoreColumns('table_name', ['col2', 'col3'], ['id'], self::BACKUP_VERSION);
                $this->cleanupColumns('table_name', ['col2', 'col3'], self::BACKUP_VERSION);
            }

            if (!Schema::hasColumns('table_name', ['col1'])) {
                $this->backupColumns('table_name', ['col1'], ['id'], self::BACKUP_VERSION);
                Schema::table('table_name', function (Blueprint $table) {
                    $table->dropColumn('col1');
                });
            }
        }, attempts: 5);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumns('table_name', ['col2', 'col3'])) {
            $this->backupColumns('table_name', ['col2', 'col3'], ['id'], self::BACKUP_VERSION);
            Schema::table('table_name', function (Blueprint $table) {
                $table->dropColumn(['col2', 'col3']);
            });
        }

        if (!Schema::hasColumns('table_name', ['col1'])) {
            Schema::table('table_name', function (Blueprint $table) {
                $table->date('col1');
            });
            $this->restoreColumns('table_name', ['col1'], ['id'], self::BACKUP_VERSION);
            $this->cleanupColumns('table_name', ['col1'], self::BACKUP_VERSION);
        }
    }
};
```

### Using the Facade

You can use the `DataSafety` facade outside of a migration context:

```php
use Meraki\Packages\DataSafety\Facades\DataSafety;

// Backup a full table
DataSafety::backupTable('users', ['id'], '2026_06_18');

// Backup specific columns
DataSafety::backupColumns('users', ['email', 'name'], ['id'], '2026_06_18');

// Restore a full table
DataSafety::restoreTable('users', ['id'], '2026_06_18');

// Restore specific columns
DataSafety::restoreColumns('users', ['email', 'name'], ['id'], '2026_06_18');

// Cleanup after restore
DataSafety::cleanupTable('users', '2026_06_18');
DataSafety::cleanupColumns('users', ['email', 'name'], '2026_06_18');

// List all backup files
$backups = DataSafety::listBackups();
// Returns: [['file' => '...', 'size' => 1234, 'created_at' => '2026-06-18 10:00:00'], ...]
```

### Artisan Commands

#### List backup files
```commandline
php artisan meraki:data-safety:list
```

#### Clean up old backup files
```commandline
# Dry run — list files older than 30 days without deleting
php artisan meraki:data-safety:cleanup --dry-run

# Delete files older than 7 days (with confirmation)
php artisan meraki:data-safety:cleanup --older-than=7

# Delete all files without confirmation prompt
php artisan meraki:data-safety:cleanup --older-than=0 --force
```

### Disabling the module

When `MERAKI_DATA_SAFETY_ENABLED=false`, the package binds a `NullDataSafetyService` that performs no operations and creates no files. Migrations using the trait will run without errors.

### Running Tests
```commandline
composer test
```
