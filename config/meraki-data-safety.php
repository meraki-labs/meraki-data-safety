<?php

return [
    'enabled'        => env('MERAKI_DATA_SAFETY_ENABLED', true),
    'disk'           => env('MERAKI_DATA_SAFETY_DISK', 'local'),
    'storage_path'   => env('MERAKI_DATA_SAFETY_PATH', 'meraki/data-safety'),
    'backup_chunk'   => (int) env('MERAKI_DATA_SAFETY_BACKUP_CHUNK', 1000),
    'restore_chunk'  => (int) env('MERAKI_DATA_SAFETY_RESTORE_CHUNK', 500),
];
