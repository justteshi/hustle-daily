import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  base: '/',
  plugins: [tailwindcss()],
  server: {
    proxy: {
      '/api': { target: process.env.VITE_DEV_BACKEND_URL || 'https://localhost', changeOrigin: true, secure: true },
      '/session': { target: process.env.VITE_DEV_BACKEND_URL || 'https://localhost', changeOrigin: true, secure: true },
    },
  },
});
