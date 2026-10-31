import { defineConfig } from "vitest/config"
import path from "node:path"

/**
 * Vitest para a lógica PURA do app (fila offline, classificação de situação) —
 * o app não tinha runner nenhum, e a fila que guarda entrega e dinheiro sem
 * sinal só era "provada" por typecheck (PIE-16). Ambiente node e só `*.test.ts`
 * para não puxar módulos React Native; storage/store/http entram por `vi.mock`.
 */
export default defineConfig({
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "src"),
            "@shared": path.resolve(__dirname, "..", "mobile-shared", "src"),
        },
    },
    test: {
        environment: "node",
        include: ["src/**/*.test.ts"],
    },
})
