<?php

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use VarsuiteCore\Http\Controllers\BackupController;
use VarsuiteCore\Http\Controllers\CoreController;
use VarsuiteCore\Http\Controllers\PackageController;
use VarsuiteCore\Http\Controllers\SSOController;
use VarsuiteCore\Http\Controllers\UserController;

Route::prefix('api/vscore')->group(function() {
    // Backups
    Route::post('site/{siteId}/backup/{backupId}/create', [BackupController::class, 'store']);

    // Core Update
    Route::post('core/update', [CoreController::class, 'update']);

    // Packages
    Route::post('packages', [PackageController::class, 'update']);
    Route::delete('packages', [PackageController::class, 'destroy']);

    // SSO
    Route::middleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
    ])->group(function() {
        Route::post('sso-token', [SSOController::class, 'store']);
        Route::get('sso', [SSOController::class, 'show']);
    });

    // User management
    Route::post('user', [UserController::class, 'store']);
    Route::put('user/{userId}', [UserController::class, 'update']);
    Route::delete('user/{userId}', [UserController::class, 'delete']);
});