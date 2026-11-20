<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Master\{BankController, BlockController, ChartOfAccountController,
    StreetController, VillageController, WorkAreaController, ZoneController};
use App\Http\Controllers\Api\V1\Property\ResidentController;
use App\Http\Controllers\Api\V1\Shared\RegionController;
use App\Http\Controllers\Api\V1\Property\{UnitController, VehicleController, WaterMeterController};
use App\Http\Controllers\Api\V1\Finance\{IplRateController, OtherFeeController, PenaltySettingController, WaterTariffController};

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
    
    Route::prefix('regions')->group(function () {
        Route::get('provinces', [RegionController::class, 'provinces']);
        Route::get('cities', [RegionController::class, 'cities']);
        Route::get('districts', [RegionController::class, 'districts']);
        Route::get('villages', [RegionController::class, 'villages']);
    });

    Route::middleware('permission:residents.view')->group(function () {
        Route::get('residents', [ResidentController::class, 'index']);
        Route::get('residents/{id}', [ResidentController::class, 'show'])->whereNumber('id');
        Route::get('residents/{id}/nik', [ResidentController::class, 'revealNik'])->whereNumber('id');
        Route::get('residents/{id}/photo', [ResidentController::class, 'photo'])->whereNumber('id');
    });

    Route::middleware('permission:residents.manage')->group(function () {
        Route::post('residents', [ResidentController::class, 'store']);
        Route::put('residents/{id}', [ResidentController::class, 'update'])->whereNumber('id');
        Route::patch('residents/{id}/active', [ResidentController::class, 'setActive'])->whereNumber('id');
        Route::post('residents/{id}/photo', [ResidentController::class, 'storePhoto'])->whereNumber('id');
        Route::delete('residents/{id}/photo', [ResidentController::class, 'destroyPhoto'])->whereNumber('id');
    });

    Route::get('residents/{id}/units', [ResidentController::class, 'units'])
        ->whereNumber('id')->middleware('permission:residents.view');

    Route::middleware('permission:units.view')->group(function () {
        Route::get('units', [UnitController::class, 'index']);
        Route::get('units/summary', [UnitController::class, 'summary']);
        Route::get('units/{id}', [UnitController::class, 'show'])->whereNumber('id');
        Route::get('units/{id}/history', [UnitController::class, 'history'])->whereNumber('id');
        Route::get('units/{unitId}/vehicles', [VehicleController::class, 'index'])->whereNumber('unitId');
        Route::get('units/{unitId}/water-meters', [WaterMeterController::class, 'index'])->whereNumber('unitId');
    });

    Route::middleware('permission:units.manage')->group(function () {
        Route::post('units', [UnitController::class, 'store']);
        Route::put('units/{id}', [UnitController::class, 'update'])->whereNumber('id');
        Route::patch('units/{id}/active', [UnitController::class, 'setActive'])->whereNumber('id');
        Route::post('units/{id}/owner', [UnitController::class, 'replaceOwner'])->whereNumber('id');
        Route::post('units/{id}/occupant', [UnitController::class, 'replaceOccupant'])->whereNumber('id');
    });

    Route::middleware('permission:vehicles.manage')->group(function () {
        Route::post('units/{unitId}/vehicles', [VehicleController::class, 'store'])->whereNumber('unitId');
        Route::put('vehicles/{id}', [VehicleController::class, 'update'])->whereNumber('id');
        Route::patch('vehicles/{id}/active', [VehicleController::class, 'setActive'])->whereNumber('id');
    });

    Route::middleware('permission:water-meters.manage')->group(function () {
        Route::post('units/{unitId}/water-meters', [WaterMeterController::class, 'store'])->whereNumber('unitId');
        Route::post('units/{unitId}/water-meters/replace', [WaterMeterController::class, 'replace'])->whereNumber('unitId');
    });

    Route::middleware('permission:tariffs.manage|invoices.view')->group(function () {
        foreach ([
            'ipl-rates'         => IplRateController::class,
            'penalty-settings'  => PenaltySettingController::class,
            'water-tariffs'     => WaterTariffController::class,
        ] as $path => $controller) {
            Route::get($path, [$controller, 'index']);
            Route::get("{$path}/current", [$controller, 'current']);
            Route::get("{$path}/{id}", [$controller, 'show'])->whereNumber('id');
        }

        Route::get('ipl-rates/simulate', [IplRateController::class, 'simulate']);
        Route::post('water-tariffs/simulate', [WaterTariffController::class, 'simulate']);

        Route::get('other-fees', [OtherFeeController::class, 'index']);
        Route::get('other-fees/{id}', [OtherFeeController::class, 'show'])->whereNumber('id');
    });

    Route::middleware('permission:tariffs.manage')->group(function () {
        foreach ([
            'ipl-rates'         => IplRateController::class,
            'penalty-settings'  => PenaltySettingController::class,
            'water-tariffs'     => WaterTariffController::class,
        ] as $path => $controller) {
            Route::post($path, [$controller, 'store']);
            Route::put("{$path}/{id}", [$controller, 'update'])->whereNumber('id');
            Route::delete("{$path}/{id}", [$controller, 'destroy'])->whereNumber('id');
        }

        Route::patch('penalty-settings/{id}/active', [PenaltySettingController::class, 'setActive'])->whereNumber('id');

        Route::post('other-fees', [OtherFeeController::class, 'store']);
        Route::put('other-fees/{id}', [OtherFeeController::class, 'update'])->whereNumber('id');
        Route::delete('other-fees/{id}', [OtherFeeController::class, 'destroy'])->whereNumber('id');
    });
});