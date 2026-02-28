<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatatanController;
use App\Http\Controllers\Api\PengumumanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('api.v1.auth.login');

    Route::middleware(['auth:sanctum', 'api-active'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll'])->name('api.v1.auth.logout-all');

        Route::get('catatan', [CatatanController::class, 'index'])->name('api.v1.catatan.index');
        Route::post('catatan', [CatatanController::class, 'store'])->name('api.v1.catatan.store');
        Route::get('catatan/trash', [CatatanController::class, 'trash'])->name('api.v1.catatan.trash');
        Route::get('catatan/{catatan}', [CatatanController::class, 'show'])
            ->whereNumber('catatan')
            ->name('api.v1.catatan.show');
        Route::match(['put', 'patch'], 'catatan/{catatan}', [CatatanController::class, 'update'])
            ->whereNumber('catatan')
            ->name('api.v1.catatan.update');
        Route::delete('catatan/{catatan}', [CatatanController::class, 'destroy'])
            ->whereNumber('catatan')
            ->name('api.v1.catatan.destroy');
        Route::patch('catatan/{catatan}/restore', [CatatanController::class, 'restore'])
            ->whereNumber('catatan')
            ->name('api.v1.catatan.restore');
        Route::delete('catatan/{catatan}/force-delete', [CatatanController::class, 'forceDelete'])
            ->whereNumber('catatan')
            ->name('api.v1.catatan.force-delete');

        Route::get('pengumuman', [PengumumanController::class, 'index'])->name('api.v1.pengumuman.index');
        Route::get('pengumuman/{broadcast}', [PengumumanController::class, 'show'])
            ->whereNumber('broadcast')
            ->name('api.v1.pengumuman.show');
    });
});
