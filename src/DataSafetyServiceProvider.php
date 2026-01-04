<?php

namespace Meraki\DataSafety;

use Illuminate\Support\ServiceProvider;
use Meraki\DataSafety\Services\BackupService;

class DataSafetyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/data_safety.php' => config_path('meraki/data_safety.php'),
        ], 'meraki.data_safety');
    }

    public function register(): void
    {
        $this->app->singleton('meraki-data-safety', function () {
            return new BackupService('');
        });
    }
}