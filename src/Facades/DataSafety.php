<?php

namespace Meraki\Packages\DataSafety\Facades;

use Illuminate\Support\Facades\Facade;
use Meraki\Packages\DataSafety\Contracts\DataSafetyServiceContract;

class DataSafety extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DataSafetyServiceContract::class;
    }
}
