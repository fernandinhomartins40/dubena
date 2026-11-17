import { MMKV } from "react-native-mmkv"
import { StateStorage } from "zustand/middleware"
import * as SecureStore from "expo-secure-store"
import { gerarChaveMmkv } from "./chaveMmkv"

/**
 * Armazenamento local cifrado (P7 — mesma estratégia do app do cliente).
 *
 * A instância MMKV é cifrada com uma `encryptionKey` aleatória, gerada uma única
 * vez e guardada no keychain/keystore do SO via expo-secure-store (nunca no MMKV
 * nem no código). Como o construtor do MMKV exige a chave de forma SÍNCRONA e o
 * SecureStore é assíncrono, a instância é criada em `initSecureStorage()` (boot,
 * antes de hidratar as stores). Até lá, um proxy mantém a API estável.
 */
const SECURE_KEY_NAME = "entregador-mmkv-key"
const STORAGE_ID = "entregador-storage"

let mmkv: MMKV | null = null

/** true quando o boot caiu no MMKV sem cifra (keystore ou gerador indisponível). */
export let storageSemCifra = false

/** Chave de 16 bytes de gerador seguro (P2-C-20) — ver `chaveMmkv.ts`. */
const generateKey = (): string => gerarChaveMmkv()

/** Obtém (ou cria e persiste) a chave de criptografia no keychain/keystore. */
const getOrCreateEncryptionKey = async (): Promise<string> => {
    const existing = await SecureStore.getItemAsync(SECURE_KEY_NAME)
    if (existing) return existing

    const key = generateKey()
    await SecureStore.setItemAsync(SECURE_KEY_NAME, key, {
        keychainAccessible: SecureStore.AFTER_FIRST_UNLOCK,
    })
    return key
}

/**
 * Inicializa o armazenamento cifrado. Deve ser chamado uma vez no boot, ANTES de
 * qualquer leitura/escrita das stores persistidas. Idempotente.
 */
export const initSecureStorage = async (): Promise<void> => {
    if (mmkv) return

    try {
        const encryptionKey = await getOrCreateEncryptionKey()
        mmkv = new MMKV({ id: STORAGE_ID, encryptionKey })
    } catch (err) {
        // eslint-disable-next-line no-console
        console.error("[storage] Falha ao inicializar MMKV cifrado; usando fallback.", err)
        // P2-C-20: registra que este aparelho está SEM cifra, para o diagnóstico
        // (a tela de suporte/telemetria pode ler `storageSemCifra`).
        storageSemCifra = true
        mmkv = new MMKV({ id: STORAGE_ID })
    }
}

const instance = (): MMKV => {
    if (!mmkv) {
        mmkv = new MMKV({ id: STORAGE_ID })
    }
    return mmkv
}

export const zustandStorage: StateStorage = {
    setItem: (key: string, value: string) => instance().set(key, value),
    getItem: (key: string) => instance().getString(key) ?? null,
    removeItem: (key: string) => instance().delete(key),
}

const storage = {
    set: (key: string, value: string) => instance().set(key, value),
    getString: (key: string) => instance().getString(key),
    delete: (key: string) => instance().delete(key),
    clearAll: () => instance().clearAll(),
}

export default storage
