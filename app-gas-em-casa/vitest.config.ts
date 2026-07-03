import { defineConfig } from "vitest/config"
import path from "node:path"

/**
 * Vitest para as unidades PURAS do app (M-5) — validadores e polyline, sem
 * dependência de React Native. Ambiente node; escopo restrito aos *.test.ts para
 * NÃO puxar módulos RN (que exigiriam o preset do Metro). O alias @ espelha o
 * tsconfig do app.
 */
export default defineConfig({
    resolve: {
        alias: { "@": path.resolve(__dirname, "src") },
    },
    test: {
        environment: "node",
        include: ["src/**/*.test.ts"],
    },
})
