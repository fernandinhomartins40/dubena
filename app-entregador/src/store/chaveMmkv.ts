/**
 * Chave de cifra do MMKV (PIE P2-C-20).
 *
 * Antes: 64 caracteres hex de `Math.random`. Dois problemas: `Math.random` não é
 * gerador criptográfico, e o react-native-mmkv 3.x aceita no máximo 16 bytes de
 * chave (`Types.ts`: "Encryption keys can have a maximum length of 16 bytes") —
 * o núcleo C++ usa só os 16 primeiros, então a força real eram 16 hex (64 bits)
 * de um gerador previsível.
 *
 * Agora: 16 caracteres ASCII (cada um 6 bits, 96 bits no total) tirados do
 * gerador NATIVO de uuid que o expo-modules-core instala em `globalThis.expo`
 * (SecureRandom no Android, UUID() no iOS). Já está no binário: nenhuma
 * dependência nativa nova, nenhum build. Sem gerador seguro, lança — quem chama
 * registra e decide; uma chave fraca em silêncio é pior.
 *
 * Instalações antigas mantêm a chave que já está no keystore (trocar tornaria
 * o MMKV ilegível e apagaria a fila offline do entregador).
 */

const ALFABETO = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_" // 64 símbolos

/** Posições fixas do uuid v4 (versão e variante) — não são aleatórias. */
const POSICOES_FIXAS = new Set([12, 16])

export function gerarChaveMmkv(uuidv4: (() => string) | undefined = (globalThis as any)?.expo?.uuidv4): string {
    if (typeof uuidv4 !== "function") {
        throw new Error("Gerador seguro indisponível (globalThis.expo.uuidv4).")
    }

    const bytes: number[] = []
    while (bytes.length < 16) {
        const hex = String(uuidv4())
            .replace(/-/g, "")
            .split("")
            .filter((_, i) => !POSICOES_FIXAS.has(i))
            .join("")
        if (!/^[0-9a-f]{30}$/i.test(hex)) throw new Error("uuid inesperado do gerador nativo.")
        for (let i = 0; i + 2 <= hex.length && bytes.length < 16; i += 2) bytes.push(parseInt(hex.slice(i, i + 2), 16))
    }
    // 256 é múltiplo de 64: o módulo não enviesa o sorteio.
    return bytes.map((b) => ALFABETO[b % 64]).join("")
}
