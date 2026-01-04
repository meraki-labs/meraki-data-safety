## Meraki Data Safety

### Purpose
#### Merika helps to manage the version of data

### Preparation
```commandline
    composer require merakilab/meraki-data-safety
    php artisan vendor:publish --tag=meraki-data-safety-migrations
    php artisan migrate
```

### Backup / restores a full table before migration down
```php
use Meraki\DataSafety\Traits\MigrationDataSafety;

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

        //Sync data backup to the table (if exists)
        $this->restoreTable('table_name', ['id'], self::BACKUP_VERSION);

        DB::statement("SELECT setval('table_name_id_seq', (SELECT MAX(id) FROM table_name))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //Backup before drop table
        $this->backupTable('table_name', ['id'], self::BACKUP_VERSION);

        Schema::dropIfExists('table_name');
    }
};
```

### Backup / restore data from columns
```php
use Meraki\DataSafety\Traits\MigrationDataSafety;

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
                $this->restoreColumn('table_name', ['id'], ['col2', 'col3'], self::BACKUP_VERSION);
            }

            if (!Schema::hasColumns('table_name', ['col1'])) {
                $this->backupColumn('table_name', ['id'], ['col1'], self::BACKUP_VERSION);
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
            $this->backupColumn('table_name', ['id'], ['col2', 'col3'], self::BACKUP_VERSION);
            Schema::table('table_name', function (Blueprint $table) {
                $table->dropColumn(['col2', 'col3']);
            });
        }

        if (!Schema::hasColumns('table_name', ['col1'])) {
            Schema::table('table_name', function (Blueprint $table) {
                $table->date('col1');
            });
            $this->restoreColumn('table_name', ['id'], ['col1'], self::BACKUP_VERSION);
        }
    }
};
```