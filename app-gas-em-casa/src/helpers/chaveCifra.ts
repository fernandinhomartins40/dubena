/**
 * P2-B-18 — chave do MMKV cifrado.
 *
 * Antes: 64 dígitos hex de `Math.random` (não é gerador criptográfico), e o
 * comentário prometia 256 bits. O MMKV usa no máximo 16 BYTES da chave, então o
 * que valia eram 16 caracteres hex — 64 bits de um PRNG previsível.
 *
 * Agora: a fonte é o UUID v4 NATIVO do `expo-modules-core` (Android:
 * `UUID.randomUUID()`, que usa `SecureRandom`; iOS: `NSUUID`), que já vem com o
 * Expo — nenhuma dependência nativa nova. Dos 32 hex de cada UUID, 2 posições
 * são fixas (versão e variante); as outras 30 são aleatórias. Os 16 caracteres
 * da chave saem de um alfabeto de 64 símbolos (6 bits cada) = 96 bits, todos
 * dentro dos 16 bytes que o MMKV de fato usa.
 *
 * Instalação existente mantém a chave antiga (está no keystore); só chave nova
 * usa isto.
 */
const ALFABETO = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_"

/** Os 30 nibbles aleatórios de um UUID v4 (tira hífens, versão e variante). */
function nibblesAleatorios(uuid: string): string {
    const hex = uuid.replace(/-/g, "").toLowerCase()
    if (!/^[0-9a-f]{32}$/.test(hex)) throw new Error("UUID inválido para a chave de cifra.")
    return hex.slice(0, 12) + hex.slice(13, 16) + hex.slice(17)
}

export function gerarChaveCifra(uuidv4: () => string): string {
    let nibbles = ""
    while (nibbles.length < 24) nibbles += nibblesAleatorios(uuidv4())

    let chave = ""
    // 3 nibbles (12 bits) → 2 símbolos de 6 bits.
    for (let i = 0; i < 24; i += 3) {
        const v = parseInt(nibbles.slice(i, i + 3), 16)
        chave += ALFABETO[v >> 6] + ALFABETO[v & 63]
    }
    return chave
}
