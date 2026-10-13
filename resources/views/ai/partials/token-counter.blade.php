@php
    $tokenText = static fn (array $tokens, string $key): string => ($tokens['status'] ?? 'unknown') === 'unknown'
        ? '—' : (($tokens['status'] ?? '') === 'partial' ? '≥' : '').number_format($tokens[$key] ?? 0);
    $measurementLabel = match ($latestRunTokens['status'] ?? 'unknown') {
        'complete' => 'Tercatat', 'partial' => 'Sebagian tercatat', default => 'Belum tercatat',
    };
    $costProfile = config('ai_cost.enabled')
        && strtolower((string) config('services.ai_provider')) === 'deepseek'
        && in_array(strtolower((string) config('services.deepseek.model')), config('ai_cost.models', []), true)
        ? config('ai_cost.profile') : null;
@endphp
<div class="ai-token-counter" data-ai-token-counter
    @if($costProfile) data-cost-profile="{{ json_encode($costProfile) }}" @endif
    data-latest-status="{{ $latestRunTokens['status'] ?? 'unknown' }}"
    data-latest-prompt="{{ $latestRunTokens['prompt'] ?? 0 }}"
    data-latest-completion="{{ $latestRunTokens['completion'] ?? 0 }}"
    data-latest-total="{{ $latestRunTokens['total'] ?? 0 }}"
    data-session-status="{{ $sessionTokens['status'] ?? 'unknown' }}"
    data-session-prompt="{{ $sessionTokens['prompt'] ?? 0 }}"
    data-session-completion="{{ $sessionTokens['completion'] ?? 0 }}"
    data-session-total="{{ $sessionTokens['total'] ?? 0 }}">
    <button type="button" class="ai-token-pill" data-ai-token-trigger aria-label="Akumulasi token sesi" aria-haspopup="true" aria-expanded="false" title="Penggunaan token sesi: input / output">
        <span class="ai-token-icon" aria-hidden="true"><x-ai.icon name="tokens" /></span>
        <span class="ai-token-text">
            <span class="ai-token-in" data-ai-token-in title="Akumulasi Input / Prompt Sesi">{{ $tokenText($sessionTokens, 'prompt') }} in</span>
            <span class="ai-token-divider" aria-hidden="true">/</span>
            <span class="ai-token-out" data-ai-token-out title="Akumulasi Output / Completion Sesi">{{ $tokenText($sessionTokens, 'completion') }} out</span>
        </span>
    </button>
    <div class="ai-token-popover" data-ai-token-popover hidden role="dialog" aria-label="Penggunaan token">
        <div class="ai-token-popover-head">
            <strong>Pengiriman Terakhir</strong>
            <span class="ai-token-popover-status">
                <span class="ai-token-badge" data-ai-token-measurement>{{ $measurementLabel }}</span>
                @if($costProfile)
                    <span class="ai-token-cost" data-ai-token-cost-latest aria-label="Estimasi biaya belum tersedia" title="Estimasi biaya pengiriman terakhir pada tarif maksimum">—</span>
                @endif
            </span>
        </div>
        <div class="ai-token-stats">
            <div class="ai-token-stat-row">
                <span class="ai-token-label">Input (Prompt)</span>
                <span class="ai-token-stat-values">
                    <strong data-ai-token-detail-in>{{ $tokenText($latestRunTokens, 'prompt') }}</strong>
                    @if($costProfile)
                        <span class="ai-token-cost" data-ai-token-cost-input aria-label="Estimasi biaya input belum tersedia">—</span>
                    @endif
                </span>
            </div>
            <div class="ai-token-stat-row">
                <span class="ai-token-label">Output (Jawaban)</span>
                <span class="ai-token-stat-values">
                    <strong data-ai-token-detail-out>{{ $tokenText($latestRunTokens, 'completion') }}</strong>
                    @if($costProfile)
                        <span class="ai-token-cost" data-ai-token-cost-output aria-label="Estimasi biaya output belum tersedia">—</span>
                    @endif
                </span>
            </div>
            <div class="ai-token-stat-row ai-token-stat-total">
                <span class="ai-token-label">Total Pengiriman</span>
                <strong data-ai-token-detail-total>{{ $tokenText($latestRunTokens, 'total') }}</strong>
            </div>
            <div class="ai-token-stat-row" data-ai-token-draft-row hidden>
                <span class="ai-token-label">Estimasi teks draft</span>
                <strong data-ai-token-draft-estimate></strong>
            </div>
        </div>
        <div class="ai-token-popover-divider"></div>
        <div class="ai-token-stat-row ai-token-session-row">
            <span class="ai-token-label">Akumulasi Sesi Ini</span>
            <span class="ai-token-stat-values">
                <span class="ai-token-session-total" data-ai-token-session-total>{{ $tokenText($sessionTokens, 'total') }} tok</span>
                @if($costProfile)
                    <span class="ai-token-cost" data-ai-token-cost-session aria-label="Estimasi biaya sesi belum tersedia">—</span>
                @endif
            </span>
        </div>
        @if($costProfile)
            @include('ai.partials.token-cost-summary')
        @endif
    </div>
</div>
