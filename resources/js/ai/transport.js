export async function sendJson(url, method, payload = null) {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: payload === null ? null : JSON.stringify(payload),
    });
    const data = await response.json().catch(() => null);
    if (!response.ok || data?.status === 'error') {
        const errors = {
            401: 'Sesi masuk telah berakhir. Muat ulang halaman untuk masuk kembali.',
            419: 'Sesi halaman telah berakhir. Muat ulang sebelum mencoba lagi.',
            429: 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.',
        };
        throw new Error(errors[response.status] ?? data?.message ?? 'Permintaan belum berhasil. Coba kembali sebentar lagi.');
    }
    return data;
}

export async function getJson(url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    const data = await response.json().catch(() => null);
    if (!response.ok && response.status !== 202) {
        throw new Error(data?.message ?? 'Status proses belum dapat diperiksa.');
    }

    return data;
}

export function postJson(url, payload) {
    return sendJson(url, 'POST', payload);
}
