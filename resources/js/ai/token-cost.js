const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', minimumFractionDigits: 2, maximumFractionDigits: 2,
});

function validProfile(profile) {
    return profile && ['input_usd_per_million', 'output_usd_per_million', 'usd_to_idr']
        .every(key => typeof profile[key] === 'number' && Number.isFinite(profile[key]) && profile[key] > 0);
}

/** Reprice recorded tokens at the reference tariff; never interpret this as a bill. */
export function estimateTokenCost(tokens, profile, portion = 'total') {
    if (!validProfile(profile) || !['complete', 'partial'].includes(tokens?.status)) return null;
    if (![tokens.prompt, tokens.completion].every(value => Number.isSafeInteger(value) && value >= 0)) return null;
    if (!['input', 'output', 'total'].includes(portion)) return null;

    // Completion already includes reasoning. Cache discounts are intentionally excluded.
    const input = portion === 'output' ? 0 : tokens.prompt * profile.input_usd_per_million;
    const output = portion === 'input' ? 0 : tokens.completion * profile.output_usd_per_million;
    const amount = (input + output) * profile.usd_to_idr / 1_000_000;
    return Number.isFinite(amount) ? amount : null;
}

export function costDisplay(tokens, profile, portion = 'total') {
    const amount = estimateTokenCost(tokens, profile, portion);
    if (amount === null) return { text: '—', label: 'Estimasi biaya belum tersedia' };

    const formatted = rupiah.format(amount).replace(/\u00a0/g, '');
    const partial = tokens.status === 'partial';
    return {
        text: `≈${formatted}${partial ? '*' : ''}`,
        label: `Estimasi biaya ${formatted}${partial ? ', hanya sebagian penggunaan tercatat' : ''}`,
    };
}

/** Optional presentation adapter. Its only inputs are the token counter's existing totals. */
export function initTokenCost(counter) {
    let profile;
    try {
        profile = JSON.parse(counter.dataset.costProfile);
    } catch {
        return { update() {} };
    }

    const render = (scope, tokens, portion = 'total') => {
        const target = counter.querySelector(`[data-ai-token-cost-${scope}]`);
        if (!target) return;
        const display = costDisplay(tokens, profile, portion);
        target.textContent = display.text;
        target.setAttribute('aria-label', display.label);
    };
    const update = (tokens, sessionTokens) => {
        if (tokens) {
            render('latest', tokens);
            render('input', tokens, 'input');
            render('output', tokens, 'output');
        }
        if (sessionTokens) render('session', sessionTokens);
    };
    const storedTokens = scope => ({
        prompt: Number(counter.dataset[`${scope}Prompt`]),
        completion: Number(counter.dataset[`${scope}Completion`]),
        status: counter.dataset[`${scope}Status`] ?? 'unknown',
    });
    update(storedTokens('latest'), storedTokens('session'));

    return { update };
}
