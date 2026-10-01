@props(['name', 'value', 'checked' => false])
<input type="radio" name="{{ $name }}" value="{{ $value }}" {{ $attributes->class(['sr-only', 'ai-square-radio']) }} @checked($checked)>
<span class="ai-square-radio-mark" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6" /></svg>
</span>
