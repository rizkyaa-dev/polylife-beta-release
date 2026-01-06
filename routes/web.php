<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\{
    DashboardController,
    GuestDashboardController,
    GuestWorkspaceController,
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

Route::prefix('workspace')->middleware(['auth', 'verified', 'prevent-back-history'])->group(function () {
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
        Route::get('/', fn () => redirect()->route('admin.users.index'))->name('dashboard');
        Route::patch('users/{user}/verify', [AdminUserController::class, 'verify'])->name('users.verify');
        Route::resource('users', AdminUserController::class)->only(['index', 'edit', 'update', 'destroy']);
    });

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('workspace.home')
        : view('welcome');
})->name('landing');

require __DIR__.'/auth.php';
