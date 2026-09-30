import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';

const DEFAULT_GREEN_API_URL = 'https://api.green-api.com/v3';

// GREEN-API не отдаёт CORS-заголовки (на preflight отвечает 403),
// поэтому ходим через прокси vite-сервера: запросы уходят с того же
// origin, что и сам интерфейс.
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const target = env.VITE_GREEN_API_URL || DEFAULT_GREEN_API_URL;

  const proxy = {
    '/api': {
      target,
      changeOrigin: true,
      secure: true,
      rewrite: (path: string) => path.replace(/^\/api/, ''),
    },
  };

  return {
    plugins: [react()],
    server: { port: 5173, proxy },
    preview: { port: 4173, proxy },
  };
});