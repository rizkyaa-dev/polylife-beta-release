export function tokenDisplay(tokens = {}) {
    const status = tokens.status ?? 'unknown';
    const prefix = status === 'partial' ? '≥' : '';
    const value = key => status === 'unknown' ? '—' : `${prefix}${Number(tokens[key] ?? 0).toLocaleString()}`;
    return { prompt: value('prompt'), completion: value('completion'), total: value('total'),
        label: status === 'complete' ? 'Tercatat' : status === 'partial' ? 'Sebagian tercatat' : 'Belum tercatat' };
}
