import storage from "@/store/storage"

/**
 * Fila de escrita offline (F7) — o entregador em rota trabalha sem sinal.
 *
 * **O problema.** Rota tem sombra: túnel, prédio, zona rural. Hoje, se a baixa
 * de um pedido sai sem rede, ela simplesmente falha e o entregador precisa
 * lembrar de refazer. O MovelApp legado tem SQLite local, mas *não* resolve
 * isto — `PedidoStatusActivity:264` só grava depois do `status OK` do servidor,
 * ou seja, a baixa dele também exige rede. Ele é offline para LEITURA da rota.
 *
 * **O que esta fila faz.** Escritas do campo (aceitar, recusar, mudar status,
 * concluir, ocorrência) são gravadas localmente e enviadas quando a rede voltar.
 * O entregador segue trabalhando; a sincronização é assunto do app.
 *
 * **Idempotência é o que torna isso seguro.** Cada item carrega um
 * `Idempotency-Key` gerado no dispositivo e estável entre tentativas. Se a
 * primeira tentativa chegou ao servidor mas a resposta se perdeu, o reenvio
 * devolve o resultado da primeira em vez de criar um segundo pedido — o
 * middleware `Idempotente` do erp-novo cuida disso.
 *
 * **O que NÃO entra na fila:**
 *  - GETs (o núcleo compartilhado já faz retry com backoff);
 *  - ping de GPS (`posicao`) — posição velha não tem valor: reenviar a de vinte
 *    minutos atrás poluiria o rastro com um ponto que já não é verdade;
 *  - upload de foto/assinatura — multipart não sobrevive à serialização em JSON;
 *    fica para uma segunda etapa, com o arquivo referenciado por caminho.
 *
 * **Sem NetInfo.** O projeto não tem `@react-native-community/netinfo`, e
 * acrescentar dependência nativa só para saber se há rede obrigaria novo build
 * dos APKs. A própria tentativa de envio responde melhor a essa pergunta: erro
 * de rede é indistinguível de "sem conexão" para efeito da fila, e o laço para
 * na primeira falha de transporte.
 */

const CHAVE = "fila-offline-v1"

export interface ItemFila {
    /** Chave de idempotência: gerada uma vez, preservada em toda retentativa. */
    id: string
    endpoint: string
    method: "POST" | "PUT" | "PATCH" | "DELETE"
    data: unknown
    criadoEm: number
    tentativas: number
    /** Última mensagem de erro, para diagnóstico na tela de pendências. */
    ultimoErro?: string
}

/** Máximo de tentativas antes de o item exigir decisão do usuário. */
const MAX_TENTATIVAS = 8

/** uuid v4 sem dependência extra — só precisa ser único no dispositivo. */
export const novoId = (): string =>
    "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0
        const v = c === "x" ? r : (r & 0x3) | 0x8
        return v.toString(16)
    })

const ler = (): ItemFila[] => {
    try {
        // O MMKV só existe após initSecureStorage() (boot). Antes disso, ou se a
        // leitura falhar, a fila responde vazia em vez de derrubar a tela.
        return JSON.parse(storage.getString(CHAVE) ?? "[]") as ItemFila[]
    } catch {
        return []
    }
}

const gravar = (itens: ItemFila[]): void => {
    try {
        storage.set(CHAVE, JSON.stringify(itens))
    } catch {
        // Sem storage montado não há o que persistir; a ação segue pelo caminho
        // normal (online) ou se perde — melhor que quebrar a tela do entregador.
    }
}

/** Enfileira uma escrita para quando houver rede. Devolve o id de idempotência. */
export const enfileirar = (
    endpoint: string,
    method: ItemFila["method"],
    data: unknown,
): string => {
    const item: ItemFila = {
        id: novoId(),
        endpoint,
        method,
        data,
        criadoEm: Date.now(),
        tentativas: 0,
    }
    gravar([...ler(), item])
    return item.id
}

export const pendentes = (): ItemFila[] => ler()

export const quantidadePendente = (): number => ler().length

/** Descarta um item — usado quando o entregador desiste de uma ação travada. */
export const descartar = (id: string): void => {
    gravar(ler().filter((i) => i.id !== id))
}

export const limpar = (): void => {
    try {
        storage.delete(CHAVE)
    } catch {
        /* nada a limpar */
    }
}

export interface ResultadoSync {
    enviados: number
    falharam: number
    restantes: number
}

/**
 * Envia o que está na fila, em ordem de chegada.
 *
 * **Para no primeiro erro de rede** em vez de varrer a lista inteira: se a
 * conexão caiu, as próximas também falhariam, e insistir só gastaria bateria e
 * incrementaria `tentativas` sem motivo.
 *
 * **Erro do servidor (4xx) descarta o item.** Um payload que o servidor recusa
 * vai continuar recusando para sempre; mantê-lo na fila entupiria a
 * sincronização de tudo que vem depois. O 409 de idempotência é caso à parte:
 * significa que o servidor JÁ processou, então o item cumpriu seu papel.
 */
export const sincronizar = async (
    enviar: (item: ItemFila) => Promise<unknown>,
): Promise<ResultadoSync> => {
    let enviados = 0
    let falharam = 0

    for (const item of ler()) {
        try {
            await enviar(item)
            descartar(item.id)
            enviados++
        } catch (e: any) {
            const status = e?.status ?? e?.response?.status ?? 0

            // 409 = o servidor já tem esta operação (idempotência). Missão
            // cumprida: não é erro, é confirmação tardia.
            if (status === 409) {
                descartar(item.id)
                enviados++
                continue
            }

            // 4xx (menos 408/429): o servidor recusou o conteúdo. Reenviar não
            // muda nada e travaria a fila.
            if (status >= 400 && status < 500 && status !== 408 && status !== 429) {
                descartar(item.id)
                falharam++
                continue
            }

            // Rede ou 5xx: mantém para a próxima janela.
            const itens = ler()
            const alvo = itens.find((i) => i.id === item.id)
            if (alvo) {
                alvo.tentativas++
                alvo.ultimoErro = e?.message ?? "Falha ao sincronizar"
                if (alvo.tentativas >= MAX_TENTATIVAS) {
                    // Não descarta em silêncio: dinheiro e entrega precisam de
                    // decisão humana. Fica visível na tela de pendências.
                    alvo.ultimoErro = `Não sincronizou após ${MAX_TENTATIVAS} tentativas. ${alvo.ultimoErro ?? ""}`.trim()
                }
                gravar(itens)
            }
            falharam++
            break
        }
    }

    return { enviados, falharam, restantes: quantidadePendente() }
}
