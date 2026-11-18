<?php

use Illuminate\Support\Facades\Route;

Route::get('ping', fn () => response()->json([
    'data' => [
        'app'     => config('app.name'),
        'status'  => 'ok',
        'time'    => now()->toIso8601String(),
    ],
]));

// Tahap 1: auth
// Route::post('auth/login', ...);

Route::middleware('auth:sanctum')->group(function () {
    // semua endpoint modul ditaruh di sini
});