import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'
import path from 'node:path'

// Config de TESTE separada do build (vite.config.ts tem base/outDir de produção
// que não fazem sentido aqui). jsdom para render de componentes; setup carrega os
// matchers do @testing-library/jest-dom (FE-3 da auditoria).
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: { '@': path.resolve(__dirname, 'src') },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    include: ['src/**/*.{test,spec}.{ts,tsx}'],
  },
})
