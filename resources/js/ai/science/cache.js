import { SCIENCE_KERNEL_VERSION } from './contract.js';

const DATABASE = 'polylife-ai-science-results';
const STORE = 'entries';
const META = 'metadata';
const SCHEMA = 1;
const TTL_MS = 60 * 60 * 1000;
const MAX_ENTRIES = 32;
const MAX_RESULT_BYTES = 32 * 1024;

const request = value => new Promise((resolve, reject) => {
    value.onsuccess = () => resolve(value.result);
    value.onerror = () => reject(value.error ?? new Error('Science cache request failed.'));
});
const complete = transaction => new Promise((resolve, reject) => {
    transaction.oncomplete = resolve;
    transaction.onabort = transaction.onerror = () => reject(transaction.error ?? new Error('Science cache transaction failed.'));
});
const canonical = value => {
    if (Array.isArray(value)) return `[${value.map(canonical).join(',')}]`;
    if (value && typeof value === 'object') return `{${Object.keys(value).sort().map(key => `${JSON.stringify(key)}:${canonical(value[key])}`).join(',')}}`;
    return JSON.stringify(value);
};

/** Owner-scoped, versioned and bounded cache. Values remain untrusted server hints. */
export class ScienceResultCache {
    constructor({ indexedDB = globalThis.indexedDB, crypto = globalThis.crypto, now = Date.now } = {}) {
        if (!indexedDB || !crypto?.subtle) throw new Error('Persistent science cache is unavailable.');
        this.indexedDB = indexedDB;
        this.crypto = crypto;
        this.now = now;
        this.database = null;
    }

    async get(scope, solver, inputs) {
        await this.prepare(scope);
        const key = await this.key(scope, solver, inputs);
        const db = await this.open();
        const transaction = db.transaction(STORE, 'readwrite');
        const store = transaction.objectStore(STORE);
        const entry = await request(store.get(key));
        if (!entry || entry.expires_at <= this.now() || entry.kernel_version !== SCIENCE_KERNEL_VERSION
            || typeof entry.result_json !== 'string' || entry.result_json.length > MAX_RESULT_BYTES) {
            if (entry) store.delete(key);
            await complete(transaction);
            return null;
        }
        await complete(transaction);
        const result = JSON.parse(entry.result_json);
        return result?.kernel_version === SCIENCE_KERNEL_VERSION ? result : null;
    }

    async put(scope, solver, inputs, result) {
        if (result?.kernel_version !== SCIENCE_KERNEL_VERSION) return;
        const resultJson = JSON.stringify(result);
        if (resultJson.length > MAX_RESULT_BYTES) return;
        await this.prepare(scope);
        const key = await this.key(scope, solver, inputs);
        const db = await this.open();
        const transaction = db.transaction(STORE, 'readwrite');
        transaction.objectStore(STORE).put({ key, scope, kernel_version: SCIENCE_KERNEL_VERSION,
            created_at: this.now(), expires_at: this.now() + TTL_MS, result_json: resultJson });
        await complete(transaction);
        await this.trim();
    }

    async prepare(scope) {
        if (!/^[a-f0-9]{64}$/.test(scope)) throw new Error('Invalid science cache scope.');
        const db = await this.open();
        const transaction = db.transaction([META, STORE], 'readwrite');
        const metadata = transaction.objectStore(META);
        const owner = await request(metadata.get('owner'));
        if (owner?.scope !== scope || owner?.kernel_version !== SCIENCE_KERNEL_VERSION) {
            transaction.objectStore(STORE).clear();
            metadata.put({ key: 'owner', scope, kernel_version: SCIENCE_KERNEL_VERSION });
        }
        await complete(transaction);
    }

    async key(scope, solver, inputs) {
        const bytes = new TextEncoder().encode(`${SCIENCE_KERNEL_VERSION}\n${scope}\n${solver}\n${canonical(inputs)}`);
        const digest = await this.crypto.subtle.digest('SHA-256', bytes);
        return `${scope}:${[...new Uint8Array(digest)].map(byte => byte.toString(16).padStart(2, '0')).join('')}`;
    }

    async trim() {
        const db = await this.open();
        const transaction = db.transaction(STORE, 'readwrite');
        const index = transaction.objectStore(STORE).index('created_at');
        let retained = 0;
        let visited = 0;
        await new Promise((resolve, reject) => {
            const cursor = index.openCursor(null, 'prev');
            cursor.onerror = () => reject(cursor.error);
            cursor.onsuccess = () => {
                const row = cursor.result;
                if (!row || visited++ >= 128) { resolve(); return; }
                if (row.value.expires_at <= this.now() || retained++ >= MAX_ENTRIES) row.delete();
                row.continue();
            };
        });
        await complete(transaction);
    }

    async open() {
        if (this.database) return this.database;
        const opening = this.indexedDB.open(DATABASE, SCHEMA);
        opening.onupgradeneeded = () => {
            const db = opening.result;
            const entries = db.createObjectStore(STORE, { keyPath: 'key' });
            entries.createIndex('created_at', 'created_at');
            db.createObjectStore(META, { keyPath: 'key' });
        };
        this.database = await request(opening);
        return this.database;
    }
}
