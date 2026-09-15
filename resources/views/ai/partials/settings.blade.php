<dialog class="ai-settings-dialog" data-ai-settings aria-labelledby="ai-settings-title" @if ($errors->any()) data-has-errors @endif>
    <div class="ai-settings-heading">
        <div><h2 id="ai-settings-title">Asisten, dengan gayamu.</h2><p>Atur nama dan cara asisten menjawab.</p></div>
        <button type="button" class="ai-icon-button" data-ai-settings-close aria-label="Tutup pengaturan"><x-ai.icon name="close" /></button>
    </div>
    <form action="{{ route('ai.settings.update') }}" method="POST" class="ai-settings-form">
        @csrf
        @if ($errors->any())
            <div class="ai-request-error" role="alert">{{ $errors->first() }}</div>
        @endif
        <label for="ai-assistant-name">Nama asisten</label>
        <input id="ai-assistant-name" name="assistant_name" value="{{ old('assistant_name', $assistant->assistant_name) }}" required maxlength="50">
        <fieldset>
            <legend>Gaya bicara</legend>
            <div class="ai-tone-options">
                @foreach (['friendly_peer' => ['Sahabat mahasiswa', 'Hangat dan suportif'], 'casual' => ['Santai', 'Akrab dan langsung'], 'formal' => ['Formal', 'Rapi dan terstruktur'], 'strict_coach' => ['Mentor disiplin', 'Tegas dan fokus']] as $tone => [$label, $description])
                    <label class="ai-tone-option">
                        <input type="radio" name="personality_tone" value="{{ $tone }}" @checked(old('personality_tone', $assistant->personality_tone) === $tone)>
                        <span><strong>{{ $label }}</strong><span>{{ $description }}</span></span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        @if ($thinkingSupported)
            <fieldset>
                <legend>Thinking effort</legend>
                <p class="ai-field-help">Atur kedalaman penalaran asisten. Tingkat yang lebih tinggi dapat membutuhkan waktu lebih lama.</p>
                <div class="ai-effort-options">
                    @foreach ($thinkingEfforts as $effort)
                        <label class="ai-tone-option">
                            <input type="radio" name="thinking_effort" value="{{ $effort->value }}" @checked(old('thinking_effort', $activeThinkingEffort->value) === $effort->value)>
                            <span><strong>{{ $effort->label() }}</strong><span>{{ $effort->description() }}</span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @else
            <input type="hidden" name="thinking_effort" value="{{ $activeThinkingEffort->value }}">
        @endif
        <label for="ai-instructions">Instruksi tambahan <span>(opsional)</span></label>
        <textarea id="ai-instructions" name="custom_instructions" rows="3" maxlength="1000" placeholder="Misalnya, jawab singkat dan gunakan bahasa Indonesia.">{{ old('custom_instructions', $assistant->custom_instructions) }}</textarea>
        <div class="ai-settings-actions">
            <button type="button" class="ai-button ai-button-quiet" data-ai-settings-close>Batal</button>
            <button type="submit" class="ai-button ai-button-primary">Simpan pengaturan</button>
        </div>
    </form>
</dialog>
