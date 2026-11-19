<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Master\{BankController, BlockController, ChartOfAccountController,
    StreetController, VillageController, WorkAreaController, ZoneController};

Route::get('ping', fn () => response()->json([
    'data' => [
        'app'    => config('app.name'),
        'status' => 'ok',
        'time'   => now()->toIso8601String(),
    ],
]));

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1');
});

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('password', [ProfileController::class, 'updatePassword']);
        Route::get('sessions', [ProfileController::class, 'sessions']);
        Route::delete('sessions/others', [ProfileController::class, 'revokeOtherSessions']);
    });

    Route::prefix('master')->group(function () {
        $masters = [
            'blocks'            => BlockController::class,
            'streets'           => StreetController::class,
            'zones'             => ZoneController::class,
            'villages'          => VillageController::class,
            'work-areas'        => WorkAreaController::class,
            'banks'             => BankController::class,
            'chart-of-accounts' => ChartOfAccountController::class,
        ];

        foreach ($masters as $path => $controller) {
            Route::apiResource($path, $controller)
                ->parameters([$path => 'id'])
                ->only(['index', 'show']);

            Route::apiResource($path, $controller)
                ->parameters([$path => 'id'])
                ->only(['store', 'update', 'destroy'])
                ->middleware('permission:master.manage');
        }
    });

});