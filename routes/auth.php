<?php

use App\Http\Controllers\Auth\AccountBannedController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\ProfileAvatarController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('register', 'pages.auth.register')
        ->name('register')
        ->middleware('throttle:5,1');

    Volt::route('login', 'pages.auth.login')
        ->name('login')
        ->middleware('throttle:10,1');

    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request')
        ->middleware('throttle:5,1');

    Volt::route('reset-password/{token}', 'pages.auth.reset-password')
        ->name('password.reset')
        ->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::get('account/banned', AccountBannedController::class)->name('account.banned');

    Route::middleware('active-account')->group(function () {
        Route::get('profile', function (Request $request) {
            $user = $request->user();

            if ($user && $user->isAdmin()) {
                return redirect()->route($user->defaultDashboardRouteName());
            }

            return view('profile');
        })->name('profile');
        Route::get('profile/avatar/{user}', ProfileAvatarController::class)->name('profile.avatar.show');

        Route::patch('profile/theme-preference', function (Request $request) {
            $validated = $request->validate([
                'theme_preference' => ['required', 'in:system,light,dark'],
            ]);

            $user = $request->user();
            $profile = $user->profile()->firstOrNew(['user_id' => $user->id]);
            $profile->theme_preference = $validated['theme_preference'];
            $profile->save();

            return response()->json([
                'theme_preference' => $profile->theme_preference,
            ]);
        })->name('profile.theme-preference.update')->middleware('throttle:30,1');

        Volt::route('verify-email', 'pages.auth.verify-email')
            ->name('verification.notice');

        Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Volt::route('confirm-password', 'pages.auth.confirm-password')
            ->name('password.confirm');
    });

    // Logout route
    Route::post('logout', function (Request $request) {
        $user = $request->user();
        $themePreference = $request->input('theme_preference');

        if ($user && in_array($themePreference, ['light', 'dark'], true)) {
            $profile = $user->profile()->firstOrNew(['user_id' => $user->id]);
            $profile->theme_preference = $themePreference;
            $profile->save();
        }

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    })->name('logout');
});
