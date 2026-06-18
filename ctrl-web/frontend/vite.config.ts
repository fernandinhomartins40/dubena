import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'node:path'

// SPA do ERP (S1, PLANO_SPA_REACT). Servido pelo Nginx no MESMO domínio em /app.
// build → ../public/app ; base /app/ (assets resolvem sob /app).
export default defineConfig({
  plugins: [react()],
  base: '/app/',
  resolve: {
    alias: { '@': path.resolve(__dirname, 'src') },
  },
  build: {
    outDir: path.resolve(__dirname, '../public/app'),
    emptyOutDir: true,
    manifest: false,
  },
  server: {
    port: 5173,
    // Em DEV o Vite chama a API do Laravel (nginx :8082) com cookies (Sanctum).
    proxy: {
      '/api': { target: 'http://127.0.0.1:8082', changeOrigin: true },
      '/sanctum': { target: 'http://127.0.0.1:8082', changeOrigin: true },
      '/login': { target: 'http://127.0.0.1:8082', changeOrigin: true },
    },
  },
})
