<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\AffiliationBroadcastController as AdminAffiliationBroadcastController;
use App\Http\Controllers\Endmin\AdminManagementController as EndminAdminManagementController;
use App\Http\Controllers\Endmin\AffiliationController as EndminAffiliationController;
use App\Http\Controllers\Endmin\AuditLogController as EndminAuditLogController;
use App\Http\Controllers\Endmin\BroadcastVerificationController as EndminBroadcastVerificationController;
use App\Http\Controllers\Endmin\DashboardController as EndminDashboardController;
use App\Http\Controllers\Endmin\UserController as EndminUserController;
use App\Http\Controllers\{
    DashboardController,
    GuestDashboardController,
    GuestWorkspaceController,
    PengumumanController,
    PushSubscriptionController,
    KeuanganController,
    KeuanganStatistikController,
    JadwalController,
    MatkulController,
    KegiatanController,
    TugasController,
    CatatanController,
    IpkController,
    TodolistController,
    ReminderController,
    NilaiMutuController
};

Route::prefix('workspace')->middleware(['auth', 'workspace-access', 'verified', 'prevent-back-history'])->group(function () {
    // Beranda workspace sekarang di /workspace/dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('workspace.home');
    // Alias legacy untuk kompatibilitas route('dashboard') dan akses /workspace
    Route::get('/', fn () => redirect()->route('workspace.home', request()->query()))->name('dashboard');

    // Data endpoints
    Route::get('/dashboard/keuangan/data', [DashboardController::class, 'getKeuanganData'])->name('dashboard.keuangan.data');
    Route::get('/dashboard/reminders/data', [DashboardController::class, 'getRemindersData'])->name('dashboard.reminders.data');
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

    // Sumber daya utama
    Route::get('keuangan/statistik', [KeuanganStatistikController::class, 'index'])
        ->name('keuangan.statistik')
        ->middleware('verified');
    Route::resource('keuangan', KeuanganController::class)->middleware('verified');
    Route::get('jadwal/{jadwal}/hapus', [JadwalController::class, 'confirmDestroy'])->name('jadwal.confirm-delete');
    Route::resource('jadwal', JadwalController::class);
    Route::get('matkul/batch', [MatkulController::class, 'batch'])->name('matkul.batch');
    Route::post('matkul/batch/import', [MatkulController::class, 'batchImport'])->name('matkul.batch.import');
    Route::resource('matkul', MatkulController::class);
    Route::resource('kegiatan', KegiatanController::class);
    Route::resource('tugas', TugasController::class);
    Route::get('catatan/sampah', [CatatanController::class, 'trash'])->name('catatan.sampah');
    Route::patch('catatan/{catatan}/restore', [CatatanController::class, 'restore'])->name('catatan.restore');
    Route::delete('catatan/{catatan}/force-delete', [CatatanController::class, 'forceDelete'])->name('catatan.force-delete');
    Route::resource('catatan', CatatanController::class);
    Route::get('pengumuman', [PengumumanController::class, 'index'])->name('pengumuman.index');
    Route::get('pengumuman/{broadcast}', [PengumumanController::class, 'show'])->name('pengumuman.show');
    Route::resource('ipk', IpkController::class);
    Route::resource('nilai-mutu', NilaiMutuController::class)->except(['show']);
    Route::patch('todolist/{todolist}/toggle-status', [TodolistController::class, 'toggleStatus'])
        ->name('todolist.toggle-status');
    Route::resource('todolist', TodolistController::class);
    Route::resource('reminder', ReminderController::class);
});

Route::prefix('guest')->name('guest.')->group(function () {
    Route::get('/', [GuestDashboardController::class, 'index'])->name('home');
    Route::get('/keuangan', [GuestWorkspaceController::class, 'keuangan'])->name('keuangan.index');
    Route::get('/keuangan/statistik', [GuestWorkspaceController::class, 'keuanganStatistik'])->name('keuangan.statistik');
    Route::get('/jadwal', [GuestWorkspaceController::class, 'jadwal'])->name('jadwal.index');
    Route::get('/todolist', [GuestWorkspaceController::class, 'todolist'])->name('todolist.index');
    Route::get('/catatan', [GuestWorkspaceController::class, 'catatan'])->name('catatan.index');
    Route::get('/ipk', [GuestWorkspaceController::class, 'ipk'])->name('ipk.index');
    Route::get('/nilai-mutu', [GuestWorkspaceController::class, 'nilaiMutu'])->name('nilai-mutu.index');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'admin', 'prevent-back-history'])
    ->group(function () {
        Route::get('/', fn () => redirect()->route('admin.broadcasts.index'))->name('dashboard');
        Route::resource('broadcasts', AdminAffiliationBroadcastController::class)
            ->except(['destroy']);
        Route::patch('broadcasts/{broadcast}/publish', [AdminAffiliationBroadcastController::class, 'publish'])
            ->name('broadcasts.publish');
        Route::patch('broadcasts/{broadcast}/archive', [AdminAffiliationBroadcastController::class, 'archive'])
            ->name('broadcasts.archive');
        Route::delete('broadcasts/{broadcast}', [AdminAffiliationBroadcastController::class, 'destroy'])
            ->name('broadcasts.destroy');
    });

Route::prefix('endmin')
    ->name('endmin.')
    ->middleware(['auth', 'super-admin', 'prevent-back-history'])
    ->group(function () {
        Route::get('/', [EndminDashboardController::class, 'index'])->name('dashboard');

        Route::get('admins', [EndminAdminManagementController::class, 'index'])->name('admins.index');
        Route::patch('admins/{user}/promote', [EndminAdminManagementController::class, 'promote'])->name('admins.promote');
        Route::patch('admins/{user}/suspend', [EndminAdminManagementController::class, 'suspend'])->name('admins.suspend');
        Route::patch('admins/{user}/activate', [EndminAdminManagementController::class, 'activate'])->name('admins.activate');
        Route::patch('admins/{user}/demote', [EndminAdminManagementController::class, 'demote'])->name('admins.demote');

        Route::get('affiliations', [EndminAffiliationController::class, 'index'])->name('affiliations.index');
        Route::get('users/audit-logs', [EndminAuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs', fn () => redirect()->route('endmin.audit-logs.index'));
        Route::get('verifikasi-broadcasts', [EndminBroadcastVerificationController::class, 'index'])->name('broadcast-verifications.index');
        Route::get('verifikasi-broadcasts/{broadcast}', [EndminBroadcastVerificationController::class, 'show'])->name('broadcast-verifications.show');
        Route::patch('verifikasi-broadcasts/{broadcast}/archive', [EndminBroadcastVerificationController::class, 'archive'])->name('broadcast-verifications.archive');
        Route::patch('verifikasi-broadcasts/{broadcast}/unarchive', [EndminBroadcastVerificationController::class, 'unarchive'])->name('broadcast-verifications.unarchive');
        Route::delete('verifikasi-broadcasts/{broadcast}', [EndminBroadcastVerificationController::class, 'destroy'])->name('broadcast-verifications.destroy');

        Route::post('users/bulk', [EndminUserController::class, 'bulkProcess'])->name('users.bulk');
        Route::patch('users/{user}/verify', [EndminUserController::class, 'verify'])->name('users.verify');
        Route::resource('users', EndminUserController::class)->only(['index', 'edit', 'update', 'destroy']);
        Route::get('verifikasi-users', [EndminUserController::class, 'verificationIndex'])->name('verifications.index');
        Route::get('verifikasi-users/{user}/edit', [EndminUserController::class, 'verificationEdit'])->name('verifications.edit');
        Route::patch('verifikasi-users/{user}', [EndminUserController::class, 'verificationUpdate'])->name('verifications.update');
        Route::put('verifikasi-users/{user}', [EndminUserController::class, 'verificationDetailUpdate'])->name('verifications.detail-update');
    });

Route::get('/', function () {
    if (! Auth::check()) {
        return view('welcome');
    }

    $user = Auth::user();

    return redirect()->route($user->defaultDashboardRouteName());
})->name('landing');

require __DIR__.'/auth.php';
