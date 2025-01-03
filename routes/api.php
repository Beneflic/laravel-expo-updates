<?php

use Beneflic\ExpoUpdates\Controllers\ManifestController;
use Illuminate\Support\Facades\Route;

Route::get(config('expo-updates.manifest_route'), ManifestController::class);
