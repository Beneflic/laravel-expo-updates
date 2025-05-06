<?php

namespace Beneflic\ExpoUpdates;

use Illuminate\Support\Facades\Cache;

class ExpoUpdates
{
    public function new(array $data): ExpoUpdate
    {
        Cache::tags(['expo-updates'])->flush();

        return ExpoUpdate::create([
            'timestamp' => now(),
        ] + $data);
    }
}
