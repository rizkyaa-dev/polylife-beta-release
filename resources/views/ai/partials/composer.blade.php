<form class="ai-composer" data-ai-form>
    <label for="ai-message" class="sr-only">Pesan untuk {{ $assistant->assistant_name }}</label>
    <textarea id="ai-message" name="message" rows="1" maxlength="2000" required data-ai-input placeholder="Tanya atau minta bantuan {{ $assistant->assistant_name }}…" aria-describedby="ai-input-hint"></textarea>
    <div class="ai-composer-toolbar">
        @unless ($thinkingSupported)
            <span class="ai-composer-caption">Asisten PolyLife</span>
        @endunless
        <div class="ai-composer-send">
            @if ($thinkingSupported)
                <details class="ai-thinking-control" data-ai-thinking data-current-effort="{{ $activeThinkingEffort->value }}">
                    <summary aria-label="Atur tingkat thinking, saat ini {{ $activeThinkingEffort->label() }}">
                        <span>Thinking</span>
                        <strong data-ai-thinking-label>{{ $activeThinkingEffort->label() }}</strong>
                        <x-ai.icon name="chevron-down" />
                    </summary>
                    <div class="ai-thinking-popover">
                        <fieldset aria-describedby="ai-thinking-help ai-thinking-status">
                            <legend class="sr-only">Thinking effort</legend>
                            <p id="ai-thinking-help" class="sr-only">Pilih kedalaman penalaran untuk pesan berikutnya.</p>
                            <div class="ai-effort-heading"><strong data-ai-effort-preview>{{ $activeThinkingEffort->label() }}</strong><x-ai.icon name="chevron-right" /></div>
                            <label class="sr-only" for="ai-effort-slider">Tingkat penalaran: Off, Low, High, Max</label>
                            <input id="ai-effort-slider" class="ai-effort-slider" type="range" min="0" max="3" step="1" value="{{ array_search($activeThinkingEffort, $thinkingEfforts) }}" aria-valuetext="{{ $activeThinkingEffort->label() }}" aria-describedby="ai-thinking-help ai-thinking-status" data-ai-effort-slider>
                            <div class="ai-thinking-options" hidden>
                                @foreach ($thinkingEfforts as $effort)
                                    <label>
                                        <input type="radio" name="thinking_effort" value="{{ $effort->value }}" data-ai-thinking-option @checked($activeThinkingEffort === $effort)>
                                        <span><strong>{{ $effort->label() }}</strong><small>{{ $effort->description() }}</small></span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <p id="ai-thinking-status" class="ai-thinking-status" data-ai-thinking-status role="status" aria-live="polite"></p>
                    </div>
                </details>
            @endif
            <button type="submit" class="ai-send-button" data-ai-send disabled aria-label="Kirim pesan" title="Kirim pesan">
                <span data-ai-send-icon><x-ai.icon name="send" /></span>
                <span data-ai-stop-icon hidden><x-ai.icon name="stop" /></span>
            </button>
        </div>
        <span id="ai-input-hint" class="sr-only">Tekan Enter untuk mengirim atau Shift+Enter untuk membuat baris baru.</span>
    </div>
</form>
