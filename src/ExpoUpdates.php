<?php

namespace Beneflic\ExpoUpdates;

class ExpoUpdates
{
    public function new(array $data): ExpoUpdate
    {
        return ExpoUpdate::create([
            'timestamp' => now(),
        ] + $data);
    }
}
