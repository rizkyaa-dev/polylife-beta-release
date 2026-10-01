<div class="workspace-sidebar-account flex items-center gap-2.5 min-w-0 sidebar-user">
    @auth
        <a href="{{ route('profile') }}"
           class="sidebar-user-avatar grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-2xl bg-indigo-500 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-white dark:bg-indigo-400/30 dark:text-indigo-100 dark:focus:ring-indigo-300 dark:focus:ring-offset-slate-950 {{ request()->routeIs('profile') ? 'ring-2 ring-indigo-300 dark:ring-indigo-400/70' : '' }}"
           title="Buka profil"
           aria-label="Buka profil"
           data-profile-avatar-frame
           data-profile-avatar-initial="{{ $initial }}"
           data-profile-avatar-alt=""
           data-sidebar-profile-link>
            @if ($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="" class="h-full w-full object-cover">
            @else
                {{ $initial }}
            @endif
        </a>
    @else
        <div class="sidebar-user-avatar h-10 w-10 shrink-0 rounded-2xl bg-indigo-500 text-white font-semibold grid place-items-center dark:bg-indigo-400/30 dark:text-indigo-100">
            {{ $initial }}
        </div>
    @endauth

    <div class="min-w-0 flex-1 sidebar-user-text">
        @auth
            <div class="truncate text-sm leading-tight font-semibold text-slate-800 dark:text-slate-100"
                 title="{{ $displayName }}" data-profile-display-name>{{ $displayName }}</div>
            @if ($email !== '')
                <p class="truncate text-[13px] text-slate-500 dark:text-slate-400" title="{{ $email }}">{{ $email }}</p>
            @endif
        @else
            <p class="text-sm leading-tight font-medium text-slate-700 dark:text-slate-100">Mode tamu</p>
        @endauth
    </div>

    @auth
        <form method="POST" action="{{ route('logout') }}" class="shrink-0" data-theme-logout-form>
            @csrf
            <input type="hidden" name="theme_preference" value="" disabled data-theme-logout-input>
            <button type="submit"
                class="w-full inline-flex items-center justify-center rounded-xl border border-slate-200/80 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:text-white">
                Logout
            </button>
        </form>
    @endauth
</div>
