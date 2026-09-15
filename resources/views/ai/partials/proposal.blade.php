@php
    $expired = $state ? $state->isExpired() : (! empty($proposal['expires_at']) && now()->isAfter($proposal['expires_at']));
    $status = $state?->status ?? ($expired ? 'expired' : 'pending');
    if ($status === 'pending' && $expired) $status = 'expired';
    $statusLabel = match ($status) {
        'confirmed' => 'Sudah disimpan',
        'rejected' => 'Dibatalkan',
        'expired' => 'Kedaluwarsa. Minta proposal baru untuk melanjutkan.',
        default => 'Menunggu konfirmasi',
    };
    $toolName = $state?->tool_name ?? ($proposal['tool_name'] ?? '');
    $receipt = $status === 'confirmed'
        ? app(\App\Services\Ai\AiActionReceiptPresenter::class)->present($toolName)
        : null;
@endphp
<section class="ai-proposal" data-ai-proposal data-action-id="{{ $proposal['action_id'] ?? '' }}" data-signature="{{ $proposal['signature'] ?? '' }}" data-expires-at="{{ $proposal['expires_at'] ?? '' }}" data-status="{{ $status }}" aria-label="Usulan perubahan data">
    <p class="ai-proposal-status" data-proposal-status role="status">{{ $statusLabel }}</p>
    <h3 data-proposal-summary>{{ $proposal['summary'] ?? '' }}</h3>
    <p class="ai-proposal-note" data-proposal-note @if (! in_array($status, ['pending', 'confirmed'], true)) hidden @endif>
        <span data-proposal-note-text>{{ $receipt['message'] ?? 'Periksa usulan ini sebelum menyimpannya ke workspace.' }}</span>
        <a data-proposal-destination
            href="{{ $receipt['destination_url'] ?? '#' }}"
            @if (empty($receipt['destination_url'])) hidden @endif>{{ $receipt['destination_label'] ?? '' }}</a>
    </p>
    <div class="ai-proposal-actions" data-proposal-actions @if ($status !== 'pending') hidden @endif>
        <button type="button" class="ai-button ai-button-primary" data-proposal-confirm>Konfirmasi & simpan</button>
        <button type="button" class="ai-button ai-button-quiet" data-proposal-reject>Batalkan</button>
    </div>
    <p class="ai-proposal-error" data-proposal-error hidden role="alert"></p>
</section>
