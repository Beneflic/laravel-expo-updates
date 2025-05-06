<?php

namespace Beneflic\ExpoUpdates;

use Illuminate\Support\Facades\Cache;

class ExpoUpdates
{
    public function new(array $data): ExpoUpdate
    {
        return ExpoUpdate::create([
            'timestamp' => now(),
        ] + $data);
    }
}
