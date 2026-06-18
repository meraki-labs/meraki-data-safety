<?php

namespace Meraki\Packages\DataSafety;

use Illuminate\Support\ServiceProvider;
use Meraki\Packages\DataSafety\Contracts\DataSafetyServiceContract;
use Meraki\Packages\DataSafety\Services\DataSafetyService;
use Meraki\Packages\DataSafety\Services\NullDataSafetyService;

class DataSafetyServiceProvider extends ServiceProvider
{
    protected function moduleEnabled(): bool
    {
        return (bool) config('meraki-data-safety.enabled', true);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/meraki-data-safety.php', 'meraki-data-safety');

        if (! $this->moduleEnabled()) {
            $this->app->singleton(DataSafetyServiceContract::class, NullDataSafetyService::class);
            return;
        }

        $this->app->singleton(DataSafetyServiceContract::class, DataSafetyService::class);
    }

    public function boot(): void
    {
        if (! $this->moduleEnabled()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/meraki-data-safety.php' => config_path('meraki-data-safety.php'),
        ], ['meraki-config', 'meraki-data-safety-config']);

        if ($this->app->bound(\Meraki\Core\Modules\PackageRegistry::class)) {
            $registry = $this->app->make(\Meraki\Core\Modules\PackageRegistry::class);
            if (! $registry->has('meraki-data-safety')) {
                $registry->register('meraki-data-safety', [
                    'provider' => self::class,
                    'config'   => 'meraki-data-safety',
                ]);
            }
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Meraki\Packages\DataSafety\Console\Commands\ListBackupsCommand::class,
                \Meraki\Packages\DataSafety\Console\Commands\CleanupBackupsCommand::class,
            ]);
        }
    }
}
