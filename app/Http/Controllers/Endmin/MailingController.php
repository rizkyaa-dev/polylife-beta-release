<?php

namespace App\Http\Controllers\Endmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Endmin\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Throwable;

class MailingController extends Controller
{
    private const TEMPLATE_VERIFY_EMAIL = 'verify_email';

    private const TEMPLATE_RESET_PASSWORD = 'reset_password';

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $emailStatusFilter = trim((string) $request->query('email_status', ''));

        $usersQuery = User::query()
            ->select([
                'id',
                'name',
                'email',
                'is_admin',
                'role',
                'account_status',
                'email_verified_at',
                'created_at',
            ]);

        if ($search !== '') {
            $usersQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($emailStatusFilter === 'verified') {
            $usersQuery->whereNotNull('email_verified_at');
        } elseif ($emailStatusFilter === 'unverified') {
            $usersQuery->whereNull('email_verified_at');
        }

        $users = $usersQuery
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('endmin.mailing.index', [
            'users' => $users,
            'filters' => [
                'q' => $search,
                'email_status' => $emailStatusFilter,
            ],
            'templates' => $this->templates(),
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }

    public function send(Request $request, User $user)
    {
        $validated = $request->validate([
            'template' => ['required', Rule::in(array_keys($this->templates()))],
        ]);

        if (blank($user->email)) {
            return back()->withErrors(['mailing' => 'User ini belum memiliki email.']);
        }

        $template = $validated['template'];
        $templateLabel = $this->templates()[$template];

        try {
            if ($template === self::TEMPLATE_VERIFY_EMAIL) {
                $user->sendEmailVerificationNotification();
            } else {
                $status = Password::sendResetLink(['email' => $user->email]);

                if ($status !== Password::RESET_LINK_SENT) {
                    return back()->withErrors(['mailing' => __($status)]);
                }
            }
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'mailing' => 'Email belum bisa dikirim. Cek konfigurasi mail atau log aplikasi.',
            ]);
        }

        AuditLogger::log(
            actor: $request->user(),
            module: 'mailing',
            action: 'send_'.$template,
            targetUser: $user,
            context: [
                'template' => $template,
                'email' => $user->email,
            ],
        );

        return back()->with('success', "Template {$templateLabel} berhasil dikirim ke {$user->email}.");
    }

    private function templates(): array
    {
        return [
            self::TEMPLATE_VERIFY_EMAIL => 'Verifikasi Email',
            self::TEMPLATE_RESET_PASSWORD => 'Reset Password',
        ];
    }
}
