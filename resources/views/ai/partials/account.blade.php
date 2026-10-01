@auth
    <div class="ai-sidebar-account sidebar-user">
        <a href="{{ route('profile') }}"
            class="ai-sidebar-account-profile"
            title="Buka profil"
            aria-label="Buka profil"
            data-sidebar-profile-link>
            <span class="ai-sidebar-account-avatar sidebar-user-avatar"
                data-profile-avatar-frame
                data-profile-avatar-initial="{{ $initial }}"
                data-profile-avatar-alt="">
                @if ($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="" class="h-full w-full object-cover">
                @else
                    {{ $initial }}
                @endif
            </span>
            <span class="ai-sidebar-account-text sidebar-user-text">
                <strong class="ai-sidebar-account-name" data-profile-display-name>{{ $displayName }}</strong>
                @if ($email !== '')
                    <span class="ai-sidebar-account-email" title="{{ $email }}">{{ $email }}</span>
                @endif
            </span>
        </a>

        <form method="POST" action="{{ route('logout') }}"
            class="ai-sidebar-account-logout-form"
            data-theme-logout-form>
            @csrf
            <input type="hidden" name="theme_preference" value="" disabled data-theme-logout-input>
            <button type="submit"
                class="ai-sidebar-account-logout ai-icon-button"
                title="Keluar dari akun"
                aria-label="Keluar dari akun">
                <svg class="ai-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M14 8l4 4-4 4M18 12H9" />
                </svg>
            </button>
        </form>
    </div>
@endauth
