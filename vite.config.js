import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const normalizeAppUrl = (value) => {
        if (!value) {
            return null;
        }
        const normalized = value.includes('://') ? value : `https://${value}`;
        try {
            return new URL(normalized);
        } catch (error) {
            return null;
        }
    };
    const appUrl = normalizeAppUrl(env.APP_URL);
    const host = env.VITE_HOST ?? '0.0.0.0';
    const port = Number(env.VITE_PORT ?? env.PORT ?? 5173);
    const hmrHost = env.VITE_HMR_HOST ?? appUrl?.hostname ?? 'localhost';
    const hmrProtocol = env.VITE_HMR_PROTOCOL ?? appUrl?.protocol?.replace(':', '') ?? 'http';

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
        ],
        server: {
            host,
            port,
            strictPort: true,
            hmr: {
                host: hmrHost,
                port,
                protocol: hmrProtocol,
            },
        },
    };
});
