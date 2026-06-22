import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'node:path'

// SPA do ERP NOVO (erp-novo). Em produção é servida pelo Nginx do host em
// gasemcasa.com/novo/app (o host faz strip do /novo → o container vê /app).
// build → ../public/app ; base /novo/app/ (assets/rotas resolvem sob /novo/app).
export default defineConfig({
  plugins: [react()],
  base: '/novo/app/',
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
    // Em DEV o Vite faz proxy da API para o backend erp-novo local (porta 8000).
    proxy: {
      '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      '/sanctum': { target: 'http://127.0.0.1:8000', changeOrigin: true },
    },
  },
})
