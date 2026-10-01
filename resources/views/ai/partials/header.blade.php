<header class="ai-topbar">
    <div class="ai-topbar-inner">
        <div class="ai-topbar-heading">
            <button type="button" class="ai-button ai-button-quiet mobile-nav-trigger" data-mobile-sidebar-open aria-label="Buka menu percakapan"><x-ai.icon name="menu" /></button>
            <div class="ai-assistant-title">
                <span class="ai-assistant-name"><span>{{ $assistant->assistant_name }}</span></span>
                <span class="ai-assistant-tone">{{ $assistant->personaLabel() }}</span>
            </div>
        </div>
        <button type="button" class="ai-icon-button" data-ai-settings-open aria-label="Pengaturan asisten" title="Pengaturan asisten"><x-ai.icon name="settings" /></button>
    </div>
</header>
