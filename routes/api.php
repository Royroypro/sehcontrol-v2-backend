<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V2\ClientPolicyController;
use App\Http\Controllers\Api\V2\ClientPairController;
use App\Http\Controllers\Api\V2\ClientHeartbeatController;
use App\Http\Controllers\Api\V2\DevicePinController;
use App\Http\Controllers\Api\V2\AdminDeviceRegisterController;
use App\Http\Middleware\VerifyAgentSignature;

Route::prefix('v2')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Endpoints del agente
    |--------------------------------------------------------------------------
    | - PAIR: HMAC (o Bearer si ya tiene token)
    | - POLICY/HEARTBEAT: Bearer obligatorio (Sanctum)
    */

    // PAIR: permite HMAC (cuando no hay token) o Bearer (si ya existe)
    Route::middleware([VerifyAgentSignature::class, 'throttle:120,1'])->group(function () {
        Route::post('client/pair', ClientPairController::class);
    });

    // POLICY: Bearer obligatorio
    Route::middleware([VerifyAgentSignature::class, 'auth:sanctum', 'throttle:120,1'])->group(function () {
        Route::get('client/policy', ClientPolicyController::class);
        Route::post('client/heartbeat', ClientHeartbeatController::class);
    });

    /*
    |--------------------------------------------------------------------------
    | Endpoints administrativos
    |--------------------------------------------------------------------------
    */
    Route::post('device/pin', DevicePinController::class)
        ->middleware(['admin.key', 'throttle:20,1']);

    Route::post('device/register', AdminDeviceRegisterController::class)
        ->middleware(['admin.key', 'throttle:20,1']);
});
