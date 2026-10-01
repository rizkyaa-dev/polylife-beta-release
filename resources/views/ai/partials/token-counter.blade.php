@php
    $tokenText = static fn (array $tokens, string $key): string => ($tokens['status'] ?? 'unknown') === 'unknown'
        ? '—' : (($tokens['status'] ?? '') === 'partial' ? '≥' : '').number_format($tokens[$key] ?? 0);
    $measurementLabel = match ($latestRunTokens['status'] ?? 'unknown') {
        'complete' => 'Tercatat', 'partial' => 'Sebagian tercatat', default => 'Belum tercatat',
    };
@endphp
<div class="ai-token-counter" data-ai-token-counter
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
            <span class="ai-token-badge" data-ai-token-measurement>{{ $measurementLabel }}</span>
        </div>
        <div class="ai-token-stats">
            <div class="ai-token-stat-row">
                <span class="ai-token-label">Input (Prompt)</span>
                <strong data-ai-token-detail-in>{{ $tokenText($latestRunTokens, 'prompt') }}</strong>
            </div>
            <div class="ai-token-stat-row">
                <span class="ai-token-label">Output (Jawaban)</span>
                <strong data-ai-token-detail-out>{{ $tokenText($latestRunTokens, 'completion') }}</strong>
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
            <span class="ai-token-session-total" data-ai-token-session-total>{{ $tokenText($sessionTokens, 'total') }} tok</span>
        </div>
    </div>
</div>
