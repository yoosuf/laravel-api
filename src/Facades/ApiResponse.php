<?php

namespace Yoosuf\LaravelApi\Facades;

use Illuminate\Support\Facades\Facade;

class ApiResponse extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-api.response';
    }
}
