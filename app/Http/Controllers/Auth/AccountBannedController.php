<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountBannedController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse|Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isActiveAccount()) {
            return redirect()->route($user->defaultDashboardRouteName());
        }

        $reasonCode = trim((string) ($user->ban_reason_code ?? ''));
        $reasonLabels = [
            'abuse' => 'Penyalahgunaan akun',
            'spam' => 'Spam atau aktivitas berulang',
            'policy_violation' => 'Pelanggaran kebijakan',
            'bulk_ban' => 'Tindakan massal super admin',
            'admin_suspension' => 'Suspensi admin',
        ];

        $reasonLabel = $reasonLabels[$reasonCode] ?? ($reasonCode !== ''
            ? Str::of($reasonCode)->replace(['_', '-'], ' ')->headline()->toString()
            : null);

        return response()
            ->view('auth.account-banned', [
                'user' => $user,
                'reasonLabel' => $reasonLabel,
                'reasonCode' => $reasonCode !== '' ? $reasonCode : null,
                'reasonNote' => trim((string) ($user->ban_reason_text ?? '')),
                'bannedAt' => $user->banned_at,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
