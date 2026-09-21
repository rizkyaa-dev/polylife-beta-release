<?php

use App\Http\Controllers\Admin\AffiliationBroadcastController as AdminAffiliationBroadcastController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Ai\AiChatController;
use App\Http\Controllers\Ai\AiChatSessionController;
use App\Http\Controllers\Ai\AiScienceExecutionController;
use App\Http\Controllers\Ai\AiScienceWorkerBootstrapController;
use App\Http\Controllers\Ai\AiWorkspaceController;
use App\Http\Controllers\BroadcastImageController;
use App\Http\Controllers\CatatanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Endmin\AdminManagementController as EndminAdminManagementController;
use App\Http\Controllers\Endmin\AffiliationController as EndminAffiliationController;
use App\Http\Controllers\Endmin\AuditLogController as EndminAuditLogController;
use App\Http\Controllers\Endmin\BroadcastVerificationController as EndminBroadcastVerificationController;
use App\Http\Controllers\Endmin\DashboardController as EndminDashboardController;
use App\Http\Controllers\Endmin\UserController as EndminUserController;
use App\Http\Controllers\GuestDashboardController;
use App\Http\Controllers\GuestWorkspaceController;
use App\Http\Controllers\IpkController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\KeuanganBudgetController;
use App\Http\Controllers\KeuanganController;
use App\Http\Controllers\KeuanganStatistikController;
use App\Http\Controllers\MatkulController;
use App\Http\Controllers\NilaiMutuController;
use App\Http\Controllers\PengumumanController;
use App\Http\Controllers\PengumumanCreatorAvatarController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\TodolistController;
use App\Http\Controllers\TugasController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

$userWriteMiddleware = ['throttle:workspace-write', 'prevent-duplicate-write'];
$bulkWriteMiddleware = ['throttle:bulk-write', 'prevent-duplicate-write'];

Route::get('/ai/science/client-worker.js', AiScienceWorkerBootstrapController::class)
    ->name('ai.science.worker-bootstrap');

Route::get('media/broadcasts/{path}', BroadcastImageController::class)
    ->where('path', '.*')
    ->name('broadcast-images.show');

Route::prefix('workspace')->middleware(['auth', 'active-account', 'workspace-access', 'verified', 'prevent-back-history'])->group(function () use ($userWriteMiddleware, $bulkWriteMiddleware) {
    // Beranda workspace sekarang di /workspace/dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('workspace.home');
    // Alias legacy untuk kompatibilitas route('dashboard') dan akses /workspace
    Route::get('/', fn () => redirect()->route('workspace.home', request()->query()))->name('dashboard');
    Route::get('profile', function (Request $request) {
        $user = $request->user();

        if ($user && $user->isAdmin()) {
            return redirect()->route($user->defaultDashboardRouteName());
        }

        return view('profile');
    })->name('profile');

    // Data endpoints
    Route::get('/dashboard/keuangan/data', [DashboardController::class, 'getKeuanganData'])->name('dashboard.keuangan.data');
    Route::get('/dashboard/reminders/data', [DashboardController::class, 'getRemindersData'])->name('dashboard.reminders.data');
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->middleware($userWriteMiddleware)->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->middleware($userWriteMiddleware)->name('push.unsubscribe');

    // Sumber daya utama
    Route::get('keuangan/statistik', [KeuanganStatistikController::class, 'index'])
        ->name('keuangan.statistik')
        ->middleware('verified');
    Route::get('keuangan/anggaran', [KeuanganBudgetController::class, 'index'])
        ->name('keuangan.anggaran')
        ->middleware('verified');
    Route::post('keuangan/anggaran', [KeuanganBudgetController::class, 'store'])
        ->name('keuangan.anggaran.store')
        ->middleware(['verified', ...$userWriteMiddleware]);
    Route::delete('keuangan/anggaran/{budget}', [KeuanganBudgetController::class, 'destroy'])
        ->name('keuangan.anggaran.destroy')
        ->middleware(['verified', ...$userWriteMiddleware]);
    Route::resource('keuangan', KeuanganController::class)
        ->middleware('verified')
        ->middlewareFor(['store', 'update', 'destroy'], $userWriteMiddleware);
    Route::get('jadwal/manage', [JadwalController::class, 'manage'])->name('jadwal.manage');
    Route::get('jadwal/{jadwal}/hapus', [JadwalController::class, 'confirmDestroy'])->name('jadwal.confirm-delete');
    Route::resource('jadwal', JadwalController::class)
        ->middlewareFor(['store', 'update', 'destroy'], $userWriteMiddleware);
    Route::get('matkul/batch', [MatkulController::class, 'batch'])->name('matkul.batch');
    Route::post('matkul/batch/import', [MatkulController::class, 'batchImport'])->middleware($bulkWriteMiddleware)->name('matkul.batch.import');
    Route::resource('matkul', MatkulController::class)
        ->middlewareFor(['store', 'update', 'destroy'], $userWriteMiddleware);
    Route::resource('kegiatan', KegiatanController::class)
        ->middlewareFor(['store', 'update', 'destroy'], $userWriteMiddleware);
    Route::resource('tugas', TugasController::class)
        ->middlewareFor(['store', 'update', 'destroy'], $userWriteMiddleware);
    Route::get('catatan/manage', [CatatanController::class, 'manage'])->name('catatan.manage');
    Route::get('catatan/sampah', [CatatanController::class, 'trash'])->name('catatan.sampah');
    Route::post('catatan/bulk-trash', [CatatanController::class, 'bulkTrash'])->middleware($userWriteMiddleware)->name('catatan.bulk-trash');
    Route::post('catatan/bulk-restore', [CatatanController::class, 'bulkRestore'])->middleware($userWriteMiddleware)->name('catatan.bulk-restore');
    Route::post('catatan/bulk-force-delete', [CatatanController::class, 'bulkForceDelete'])->middleware($userWriteMiddleware)->name('catatan.bulk-force-delete');
    Route::patch('catatan/{catatan}/restore', [CatatanController::class, 'restore'])->middleware($userWriteMiddleware)->name('catatan.restore');
    Route::delete('catatan/{catatan}/force-delete', [CatatanController::class, 'forceDelete'])->middleware($userWriteMiddleware)->name('catatan.force-delete');
    Route::resource('catatan', CatatanController::class)
        ->middlewareFor(['store', 'update', 'destroy'], $userWriteMiddleware);
    Route::get('pengumuman', [PengumumanController::class, 'index'])->name('pengumuman.index');
    Route::post('pengumuman/read', [PengumumanController::class, 'markRead'])->middleware('throttle:workspace-write')->name('pengumuman.read');
    Route::get('pengumuman/creator-avatar/{user}', PengumumanCreatorAvatarController::class)->name('pengumuman.creator-avatar');
    Route::get('pengumuman/{broadcast}', [PengumumanController::class, 'show'])->name('pengumuman.show');
    Route::resource('ipk', IpkController::class)
        ->middlewareFor(['store', 'update', 'destroy'], $userWriteMiddleware);
    Route::resource('nilai-mutu', NilaiMutuController::class)
        ->except(['show'])
        ->middlewareFor(['store', 'update', 'destroy'], $userWriteMiddleware);
    Route::patch('todolist/{todolist}/toggle-status', [TodolistController::class, 'toggleStatus'])
        ->middleware($userWriteMiddleware)
        ->name('todolist.toggle-status');
    Route::resource('todolist', TodolistController::class)
        ->middlewareFor(['store', 'update', 'destroy'], $userWriteMiddleware);
    Route::resource('reminder', ReminderController::class)
        ->middlewareFor(['store', 'update', 'destroy'], $userWriteMiddleware);

    Route::get('ai', [AiWorkspaceController::class, 'index'])->name('ai.workspace');
    Route::get('ai/sessions/{session}/messages', [AiWorkspaceController::class, 'messages'])->whereNumber('session')->name('ai.sessions.messages');
    Route::post('ai/settings', [AiWorkspaceController::class, 'updateSettings'])->middleware($userWriteMiddleware)->name('ai.settings.update');
    Route::patch('ai/settings/thinking', [AiWorkspaceController::class, 'updateThinking'])->middleware($userWriteMiddleware)->name('ai.settings.thinking.update');
    Route::post('ai/chat', [AiChatController::class, 'sendMessage'])->middleware([...$userWriteMiddleware, 'throttle:ai-chat'])->name('ai.chat');
    Route::get('ai/runs/{run}', [AiChatController::class, 'runStatus'])->whereNumber('run')->name('ai.runs.show');
    Route::post('ai/science/{execution}/claim', [AiScienceExecutionController::class, 'claim'])->whereNumber('execution')->middleware('throttle:60,1')->name('ai.science.claim');
    Route::post('ai/science/{execution}/submit', [AiScienceExecutionController::class, 'submit'])->whereNumber('execution')->middleware('throttle:60,1')->name('ai.science.submit');
    Route::post('ai/runs/{run}/cancel', [AiChatController::class, 'cancelRun'])->whereNumber('run')->middleware($userWriteMiddleware)->name('ai.runs.cancel');
    Route::patch('ai/messages/{message}/edit', [AiChatController::class, 'editMessage'])->whereNumber('message')->middleware([...$userWriteMiddleware, 'throttle:ai-chat'])->name('ai.messages.edit');
    Route::post('ai/branches/{branch}/activate', [AiChatSessionController::class, 'activateBranch'])->whereNumber('branch')->middleware($userWriteMiddleware)->name('ai.branches.activate');
    Route::patch('ai/sessions/{session}', [AiChatSessionController::class, 'update'])->whereNumber('session')->middleware($userWriteMiddleware)->name('ai.sessions.update');
    Route::delete('ai/sessions/{session}', [AiChatSessionController::class, 'destroy'])->whereNumber('session')->middleware($userWriteMiddleware)->name('ai.sessions.destroy');
    Route::post('ai/action/confirm', [AiChatController::class, 'confirmAction'])->middleware($userWriteMiddleware)->name('ai.action.confirm');
    Route::post('ai/action/reject', [AiChatController::class, 'rejectAction'])->middleware($userWriteMiddleware)->name('ai.action.reject');
});

Route::get('ai/workspace', fn () => redirect()->route('ai.workspace'))
    ->middleware(['auth', 'active-account', 'workspace-access', 'verified', 'prevent-back-history']);

Route::prefix('guest')->name('guest.')->group(function () {
    Route::get('/', [GuestDashboardController::class, 'index'])->name('home');
    Route::get('/keuangan', [GuestWorkspaceController::class, 'keuangan'])->name('keuangan.index');
    Route::get('/keuangan/statistik', [GuestWorkspaceController::class, 'keuanganStatistik'])->name('keuangan.statistik');
    Route::get('/keuangan/anggaran', [GuestWorkspaceController::class, 'keuanganAnggaran'])->name('keuangan.anggaran');
    Route::get('/jadwal', [GuestWorkspaceController::class, 'jadwal'])->name('jadwal.index');
    Route::get('/todolist', [GuestWorkspaceController::class, 'todolist'])->name('todolist.index');
    Route::get('/catatan', [GuestWorkspaceController::class, 'catatan'])->name('catatan.index');
    Route::get('/ipk', [GuestWorkspaceController::class, 'ipk'])->name('ipk.index');
    Route::get('/nilai-mutu', [GuestWorkspaceController::class, 'nilaiMutu'])->name('nilai-mutu.index');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'active-account', 'admin', 'prevent-back-history'])
    ->group(function () use ($userWriteMiddleware) {
        Route::get('/', fn () => redirect()->route('admin.broadcasts.index'))->name('dashboard');
        Route::get('profile', AdminProfileController::class)->name('profile');
        Route::resource('broadcasts', AdminAffiliationBroadcastController::class)
            ->except(['destroy'])
            ->middlewareFor(['store', 'update'], $userWriteMiddleware);
        Route::patch('broadcasts/{broadcast}/publish', [AdminAffiliationBroadcastController::class, 'publish'])
            ->middleware($userWriteMiddleware)
            ->name('broadcasts.publish');
        Route::patch('broadcasts/{broadcast}/archive', [AdminAffiliationBroadcastController::class, 'archive'])
            ->middleware($userWriteMiddleware)
            ->name('broadcasts.archive');
        Route::delete('broadcasts/{broadcast}', [AdminAffiliationBroadcastController::class, 'destroy'])
            ->middleware($userWriteMiddleware)
            ->name('broadcasts.destroy');
    });

Route::prefix('endmin')
    ->name('endmin.')
    ->middleware(['auth', 'active-account', 'super-admin', 'prevent-back-history'])
    ->group(function () use ($userWriteMiddleware, $bulkWriteMiddleware) {
        Route::get('/', [EndminDashboardController::class, 'index'])->name('dashboard');

        Route::get('admins', [EndminAdminManagementController::class, 'index'])->name('admins.index');
        Route::patch('admins/{user}/promote', [EndminAdminManagementController::class, 'promote'])->middleware($userWriteMiddleware)->name('admins.promote');
        Route::patch('admins/{user}/suspend', [EndminAdminManagementController::class, 'suspend'])->middleware($userWriteMiddleware)->name('admins.suspend');
        Route::patch('admins/{user}/activate', [EndminAdminManagementController::class, 'activate'])->middleware($userWriteMiddleware)->name('admins.activate');
        Route::patch('admins/{user}/demote', [EndminAdminManagementController::class, 'demote'])->middleware($userWriteMiddleware)->name('admins.demote');

        Route::get('affiliations', [EndminAffiliationController::class, 'index'])->name('affiliations.index');
        Route::get('affiliations/manage', [EndminAffiliationController::class, 'manage'])->name('affiliations.manage.index');
        Route::get('affiliations/manage/create', [EndminAffiliationController::class, 'create'])->name('affiliations.manage.create');
        Route::post('affiliations/manage', [EndminAffiliationController::class, 'store'])->middleware($userWriteMiddleware)->name('affiliations.manage.store');
        Route::get('affiliations/manage/{template}/merge', [EndminAffiliationController::class, 'mergeForm'])->name('affiliations.manage.merge');
        Route::post('affiliations/manage/{template}/merge', [EndminAffiliationController::class, 'merge'])->middleware($userWriteMiddleware)->name('affiliations.manage.merge.store');
        Route::get('affiliations/manage/{template}/edit', [EndminAffiliationController::class, 'edit'])->name('affiliations.manage.edit');
        Route::put('affiliations/manage/{template}', [EndminAffiliationController::class, 'update'])->middleware($userWriteMiddleware)->name('affiliations.manage.update');
        Route::delete('affiliations/manage/{template}', [EndminAffiliationController::class, 'destroy'])->middleware($userWriteMiddleware)->name('affiliations.manage.destroy');
        Route::get('affiliations/{affiliationName}/extend', [EndminAffiliationController::class, 'extend'])->name('affiliations.extend');
        Route::post('affiliations/{affiliationName}/extend/batch', [EndminAffiliationController::class, 'batch'])->middleware($bulkWriteMiddleware)->name('affiliations.extend.batch');
        Route::patch('affiliations/requests/{affiliationRequest}/approve', [EndminAffiliationController::class, 'approve'])->middleware($userWriteMiddleware)->name('affiliations.requests.approve');
        Route::patch('affiliations/requests/{affiliationRequest}/reject', [EndminAffiliationController::class, 'reject'])->middleware($userWriteMiddleware)->name('affiliations.requests.reject');
        Route::get('users/audit-logs', [EndminAuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs', fn () => redirect()->route('endmin.audit-logs.index'));
        Route::get('verifikasi-broadcasts', [EndminBroadcastVerificationController::class, 'index'])->name('broadcast-verifications.index');
        Route::get('verifikasi-broadcasts/{broadcast}', [EndminBroadcastVerificationController::class, 'show'])->name('broadcast-verifications.show');
        Route::patch('verifikasi-broadcasts/{broadcast}/archive', [EndminBroadcastVerificationController::class, 'archive'])->middleware($userWriteMiddleware)->name('broadcast-verifications.archive');
        Route::patch('verifikasi-broadcasts/{broadcast}/unarchive', [EndminBroadcastVerificationController::class, 'unarchive'])->middleware($userWriteMiddleware)->name('broadcast-verifications.unarchive');
        Route::delete('verifikasi-broadcasts/{broadcast}', [EndminBroadcastVerificationController::class, 'destroy'])->middleware($userWriteMiddleware)->name('broadcast-verifications.destroy');

        Route::post('users/bulk', [EndminUserController::class, 'bulkProcess'])->middleware($bulkWriteMiddleware)->name('users.bulk');
        Route::patch('users/{user}/verify', [EndminUserController::class, 'verify'])->middleware($userWriteMiddleware)->name('users.verify');
        Route::resource('users', EndminUserController::class)
            ->only(['index', 'edit', 'update', 'destroy'])
            ->middlewareFor(['update', 'destroy'], $userWriteMiddleware);
        Route::get('verifikasi-users', [EndminUserController::class, 'verificationIndex'])->name('verifications.index');
        Route::get('verifikasi-users/{user}/edit', [EndminUserController::class, 'verificationEdit'])->name('verifications.edit');
        Route::patch('verifikasi-users/{user}', [EndminUserController::class, 'verificationUpdate'])->middleware($userWriteMiddleware)->name('verifications.update');
        Route::put('verifikasi-users/{user}', [EndminUserController::class, 'verificationDetailUpdate'])->middleware($userWriteMiddleware)->name('verifications.detail-update');
    });

Route::get('/', function () {
    if (! Auth::check()) {
        return view('welcome');
    }

    $user = Auth::user();

    if (! $user->isActiveAccount()) {
        return redirect()->route('account.banned');
    }

    return redirect()->route($user->defaultDashboardRouteName());
})->name('landing');

require __DIR__.'/auth.php';
