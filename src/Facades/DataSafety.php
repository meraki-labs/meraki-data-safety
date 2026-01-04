<?php

namespace Meraki\DataSafety\Facades;

use Illuminate\Support\Facades\Facade;

class DataSafety extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'meraki-data-safety';
    }
}