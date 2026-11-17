import storage from "@/store/storage"
import useAppStore from "@/store/appStore"

/**
 * Leitura offline do que o entregador precisa na rua (PIE P2-C-11).
 *
 * O React Query vivia só em memória: se o app reiniciava num lugar sem sinal,
 * o entregador perdia lista, endereço e rota — abaixo até do MovelApp legado,
 * que lia a rota offline. Aqui a última resposta boa de cada consulta vai para o
 * MMKV (cifrado) e volta como `initialData` no próximo boot.
 *
 * **Por entregador.** A chave leva o id do usuário: quem entrar no aparelho
 * depois não vê a lista do colega. O logout explícito apaga tudo.
 *
 * Não persiste o React Query inteiro de propósito: o persister oficial é
 * dependência nova (e lockfile novo); três consultas cobrem o turno.
 */

const PREFIXO = "cache-v1:"
const INDICE = "cache-v1-indice"

export interface Guardado<T> {
    dados: T
    /** Momento da resposta (ms) — vira o "mostrando dados de 14:32". */
    em: number
}

const dono = (): number | undefined => useAppStore.getState().user?.id ?? undefined

const chaveDe = (nome: string, quem = dono()) => `${PREFIXO}${quem ?? "anon"}:${nome}`

export function lerCache<T>(nome: string): Guardado<T> | undefined {
    if (dono() === undefined) return undefined
    try {
        const bruto = storage.getString(chaveDe(nome))
        return bruto ? (JSON.parse(bruto) as Guardado<T>) : undefined
    } catch {
        return undefined
    }
}

export function gravarCache<T>(nome: string, dados: T, agora = Date.now()): void {
    if (dono() === undefined) return
    try {
        const chave = chaveDe(nome)
        storage.set(chave, JSON.stringify({ dados, em: agora } satisfies Guardado<T>))
        const indice = new Set<string>(JSON.parse(storage.getString(INDICE) ?? "[]"))
        if (!indice.has(chave)) {
            indice.add(chave)
            storage.set(INDICE, JSON.stringify([...indice]))
        }
    } catch {
        /* sem storage montado: segue só com a memória */
    }
}

/** Apaga todos os caches (logout explícito). */
export function limparCaches(): void {
    try {
        for (const chave of JSON.parse(storage.getString(INDICE) ?? "[]") as string[]) storage.delete(chave)
        storage.delete(INDICE)
    } catch {
        /* nada a limpar */
    }
}

/** Envolve um `queryFn`: toda resposta boa é guardada para a leitura offline. */
export const comCache =
    <T>(nome: string, fn: () => Promise<T>) =>
    async (): Promise<T> => {
        const dados = await fn()
        gravarCache(nome, dados)
        return dados
    }

/** Opções do useQuery para começar do que ficou guardado. */
export const iniciarDoCache = <T>(nome: string) => ({
    initialData: () => lerCache<T>(nome)?.dados,
    initialDataUpdatedAt: () => lerCache<T>(nome)?.em,
})

export type EstadoLista = "carregando" | "sem-conexao" | "erro" | "vazio" | "ok"

/**
 * Qual tela mostrar (PIE P2-C-10). Antes, falha de rede caía no "Nenhuma
 * entrega nesta aba": o entregador sem sinal concluía que não tinha entrega.
 *
 * Com dados guardados e erro, o estado é "ok" — a lista aparece — e quem chama
 * mostra o aviso de desatualizado (`avisoOffline`).
 */
export function estadoDaLista(q: { isLoading: boolean; isError: boolean; error?: unknown; data?: unknown[] | null }): EstadoLista {
    const temDados = Array.isArray(q.data) && q.data.length > 0
    if (temDados) return "ok"
    if (q.isLoading) return "carregando"
    if (q.isError) return eSemConexao(q.error) ? "sem-conexao" : "erro"
    return "vazio"
}

/** Erro de transporte (sem resposta do servidor), no formato do núcleo `@shared/http`. */
export const eSemConexao = (e: unknown): boolean => {
    const status = (e as any)?.status ?? (e as any)?.response?.status
    return status === 0 || status === undefined
}

/** "Sem conexão — mostrando o que foi carregado às 14:32", ou null se está em dia. */
export function avisoOffline(q: { isError: boolean; error?: unknown; dataUpdatedAt?: number }): string | null {
    if (!q.isError || !q.dataUpdatedAt) return null
    const d = new Date(q.dataUpdatedAt)
    const hora = `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`
    return eSemConexao(q.error)
        ? `Sem conexão — mostrando o que foi carregado às ${hora}.`
        : `Não foi possível atualizar — mostrando o que foi carregado às ${hora}.`
}
