<?php

namespace Beneflic\ExpoUpdates\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Beneflic\ExpoUpdates\ExpoUpdates
 */
class ExpoUpdates extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Beneflic\ExpoUpdates\ExpoUpdates::class;
    }
}
