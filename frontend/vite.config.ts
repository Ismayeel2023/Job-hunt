import react from '@vitejs/plugin-react';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';

// The Laravel backend. Forwarding /api and /sanctum to it keeps every request same-origin,
// so the session cookie and CSRF protection work without any CORS setup.
const backend = {
    target: process.env.JOBHUNT_BACKEND_URL ?? 'http://127.0.0.1:8000',
    // Keep the Host header, so links the backend builds (pagination, PDFs) point at this port.
    changeOrigin: false,
};

const server = {
    host: '127.0.0.1',
    port: 5180,
    strictPort: true,
    proxy: { '/api': backend, '/sanctum': backend },
};

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
    },
    server,
    preview: server,
});
