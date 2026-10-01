<form class="ai-composer" data-ai-form>
    <label for="ai-message" class="sr-only">Pesan untuk {{ $assistant->assistant_name }}</label>
    <textarea id="ai-message" name="message" rows="1" maxlength="{{ \App\Services\Ai\AiMessageLimits::MAX_CHARACTERS }}" required data-ai-input placeholder="Tanya atau minta bantuan…" aria-describedby="ai-input-hint" title="Enter untuk kirim · Shift+Enter untuk baris baru"></textarea>
    <div class="ai-composer-toolbar">
        <div class="ai-composer-send">
            @include('ai.partials.token-counter')
            @if ($thinkingSupported)
                @include('ai.partials.thinking-control')
            @endif
            <button type="submit" class="ai-send-button" data-ai-send disabled aria-label="Kirim pesan" title="Kirim pesan">
                <span data-ai-send-icon><x-ai.icon name="send" /></span>
                <span data-ai-stop-icon hidden><x-ai.icon name="stop" /></span>
            </button>
        </div>
        <span id="ai-input-hint" class="sr-only">Tekan Enter untuk mengirim atau Shift+Enter untuk membuat baris baru.</span>
    </div>
</form>
