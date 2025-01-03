<?php

namespace Beneflic\ExpoUpdates;

use Illuminate\Database\Eloquent\Model;

class ExpoUpdate extends Model
{
    protected $fillable = [
        'id',
        'runtime_version',
        'channel',
        'type',
        'timestamp',
        'metadata',
        'expo_config',
    ];

    protected $casts = [
        'metadata' => 'array',
        'expo_config' => 'array',
    ];
}
