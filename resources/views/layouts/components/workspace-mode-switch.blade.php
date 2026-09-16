<nav class="workspace-mode-switch" aria-label="Mode PolyLife">
    <a href="{{ route('workspace.home') }}" class="workspace-mode-link" @if (! $aiMode) aria-current="true" @else data-instant-workspace-navigation @endif title="Workspace" aria-label="Mode Workspace">
        <x-ai.icon name="workspace" />
        <span>Workspace</span>
    </a>
    <a href="{{ route('ai.workspace') }}" class="workspace-mode-link" @if ($aiMode) aria-current="true" @else data-instant-workspace-navigation @endif title="AI Assistant" aria-label="Mode AI">
        <x-ai.icon name="spark" />
        <span>AI</span>
    </a>
</nav>
