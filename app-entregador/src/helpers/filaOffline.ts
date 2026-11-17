import storage from "@/store/storage"
import { apagarArquivos, type ArquivoUpload } from "@/helpers/arquivosFila"

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
 *
 * **Multipart entra (P2-C-05).** Concluir a entrega e registrar ocorrência levam
 * foto/assinatura e ficavam fora da fila — justamente a ação central do dia
 * exigia sinal. Agora o item guarda os campos de texto e o CAMINHO dos anexos
 * (copiados para `documentDirectory` por `arquivosFila`); o FormData é remontado
 * a cada tentativa, com a mesma chave.
 *
 * **Sem NetInfo.** O projeto não tem `@react-native-community/netinfo`, e
 * acrescentar dependência nativa só para saber se há rede obrigaria novo build
 * dos APKs. A própria tentativa de envio responde melhor a essa pergunta: erro
 * de rede é indistinguível de "sem conexão" para efeito da fila, e o laço para
 * na primeira falha de transporte.
 */

const CHAVE = "fila-offline-v1"
const CHAVE_ATENCAO = "fila-offline-atencao-v1"

export interface ItemFila {
    /** Chave de idempotência: gerada uma vez, preservada em toda retentativa. */
    id: string
    endpoint: string
    method: "POST" | "PUT" | "PATCH" | "DELETE"
    data: unknown
    criadoEm: number
    tentativas: number
    /** Tentativas em que o servidor respondeu com erro (5xx/409) — não conta falta de sinal. */
    falhasNoServidor?: number
    /** Última mensagem de erro, para diagnóstico na tela de pendências. */
    ultimoErro?: string
    /**
     * Quem gerou a ação. Um 401 preserva a fila (PIE R2-04); se OUTRO
     * entregador entrar no aparelho, os itens do anterior não podem sair com o
     * token dele — a ocorrência ou a venda ficaria atribuída à pessoa errada.
     */
    dono?: number
    /**
     * Escrita multipart: campos de texto + anexos por caminho local. Quando
     * presente, `data` é ignorado e o envio monta um FormData.
     */
    formulario?: FormularioFila
}

export interface FormularioFila {
    campos: Record<string, string>
    arquivos: Record<string, ArquivoUpload>
}

/** Máximo de tentativas antes de o item exigir decisão do usuário. */
const MAX_TENTATIVAS = 8

/**
 * uuid v4. Prefere o gerador NATIVO que o expo-modules-core já instala
 * (`globalThis.expo.uuidv4`, SecureRandom/UUID() do SO) — está no binário,
 * não exige dependência nem build novo. Fora do app (testes), cai no
 * Math.random: aqui a chave só precisa ser única no dispositivo.
 */
export const novoId = (): string => {
    const nativo = (globalThis as any)?.expo?.uuidv4
    if (typeof nativo === "function") {
        try {
            return String(nativo())
        } catch {
            /* cai no gerador local */
        }
    }
    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0
        const v = c === "x" ? r : (r & 0x3) | 0x8
        return v.toString(16)
    })
}

const lerDe = (chave: string): ItemFila[] => {
    try {
        // O MMKV só existe após initSecureStorage() (boot). Antes disso, ou se a
        // leitura falhar, a fila responde vazia em vez de derrubar a tela.
        return JSON.parse(storage.getString(chave) ?? "[]") as ItemFila[]
    } catch {
        return []
    }
}

const gravarEm = (chave: string, itens: ItemFila[]): void => {
    try {
        storage.set(chave, JSON.stringify(itens))
    } catch {
        // Sem storage montado não há o que persistir; a ação segue pelo caminho
        // normal (online) ou se perde — melhor que quebrar a tela do entregador.
    }
}

const ler = (): ItemFila[] => lerDe(CHAVE)
const gravar = (itens: ItemFila[]): void => gravarEm(CHAVE, itens)

/**
 * Tira o item da fila e o guarda em "precisa de atenção" (PIE P2-C-06).
 *
 * Antes, o que o servidor recusava era apagado em silêncio: o entregador achava
 * que a entrega ou a venda estava registrada e só descobria no acerto. E o item
 * com 5xx permanente ficava no topo para sempre, travando tudo que vinha depois.
 * Aqui ele sai do caminho da fila sem sumir — a decisão é de uma pessoa.
 */
const moverParaAtencao = (item: ItemFila, motivo: string): void => {
    gravar(ler().filter((i) => i.id !== item.id))
    gravarEm(CHAVE_ATENCAO, [...lerDe(CHAVE_ATENCAO), { ...item, ultimoErro: motivo }])
}

/** Mensagem que o servidor deu (Laravel manda `message`), não a do axios. */
export const mensagemDoErro = (e: any, padrao = "Falha ao sincronizar"): string =>
    e?.response?.data?.message ?? e?.message ?? padrao

/**
 * Enfileira uma escrita para quando houver rede. Devolve o id de idempotência.
 *
 * `id` deve ser a chave da tentativa que falhou: um timeout não prova que o
 * servidor não gravou, e só a MESMA chave faz o reenvio devolver a resposta
 * original em vez de repetir o efeito (PIE R2-03 — com chave nova, a fila
 * duplicava solicitação de venda, cliente e ocorrência).
 */
export const enfileirar = (
    endpoint: string,
    method: ItemFila["method"],
    data: unknown,
    id: string = novoId(),
    dono?: number,
    formulario?: FormularioFila,
): string => {
    const item: ItemFila = {
        id,
        endpoint,
        method,
        data,
        criadoEm: Date.now(),
        tentativas: 0,
        dono,
        ...(formulario ? { formulario } : {}),
    }
    gravar([...ler(), item])
    return item.id
}

export const pendentes = (): ItemFila[] => ler()

export const quantidadePendente = (): number => ler().length

/**
 * Tira o item da fila (enviado, ou o entregador desistiu) e apaga os anexos
 * dele — sem isso cada foto enviada ficava para sempre no aparelho.
 */
export const descartar = (id: string): void => {
    gravar(ler().filter((i) => i.id !== id))
    void apagarArquivos(id)
}

/** Ações que não serão reenviadas sozinhas e esperam o entregador. */
export const precisamDeAtencao = (): ItemFila[] => lerDe(CHAVE_ATENCAO)

/** O entregador viu a recusa (e resolveu com a central): tira da lista. */
export const dispensarAtencao = (id: string): void => {
    gravarEm(CHAVE_ATENCAO, lerDe(CHAVE_ATENCAO).filter((i) => i.id !== id))
    void apagarArquivos(id)
}

export const limpar = (): void => {
    for (const item of [...ler(), ...lerDe(CHAVE_ATENCAO)]) void apagarArquivos(item.id)
    try {
        storage.delete(CHAVE)
        storage.delete(CHAVE_ATENCAO)
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
 * **Recusa do servidor (4xx) sai da fila, mas não some:** vai para "precisa de
 * atenção". Um payload recusado continuará recusado; mantê-lo no topo entupiria
 * tudo que vem depois, e apagá-lo escondia do entregador uma entrega que não
 * foi registrada (PIE P2-C-06).
 *
 * **409 é transitório.** O middleware `Idempotente` devolve a resposta gravada
 * quando a chave já foi processada — 409 só sai com a chave AINDA em execução
 * (ou com payload diferente). Tratá-lo como sucesso perdia a ação quando a
 * primeira execução terminava em erro.
 *
 * **Uma sincronização por vez no processo.** O hook é montado em mais de uma
 * tela, cada uma com sua trava; duas varreduras simultâneas enviavam o mesmo
 * item duas vezes e geravam exatamente o 409 acima.
 */
let emCurso: Promise<ResultadoSync> | null = null

export const sincronizar = (
    enviar: (item: ItemFila) => Promise<unknown>,
    donoAtual?: number,
): Promise<ResultadoSync> => {
    if (!emCurso) {
        emCurso = varrer(enviar, donoAtual).finally(() => {
            emCurso = null
        })
    }
    return emCurso
}

const varrer = async (
    enviar: (item: ItemFila) => Promise<unknown>,
    donoAtual?: number,
): Promise<ResultadoSync> => {
    let enviados = 0
    let falharam = 0

    for (const item of ler()) {
        // Item de outro entregador fica parado (visível nas pendências), não
        // é enviado com o token de quem está logado agora.
        if (item.dono !== undefined && item.dono !== donoAtual) {
            continue
        }
        try {
            await enviar(item)
            descartar(item.id)
            enviados++
        } catch (e: any) {
            const status = e?.status ?? e?.response?.status ?? 0

            // 401: o TOKEN venceu, não o conteúdo. Descartar aqui apagava todas
            // as pendências do entregador (PIE R2-04). Para e espera o novo
            // login — a próxima sincronização reenvia com a mesma chave.
            if (status === 401) {
                falharam++
                break
            }

            // 4xx (menos 408/409/429): o servidor recusou o conteúdo.
            if (status >= 400 && status < 500 && status !== 408 && status !== 409 && status !== 429) {
                moverParaAtencao(item, mensagemDoErro(e))
                falharam++
                continue
            }

            // Rede, 5xx ou 409 em execução: mantém para a próxima janela.
            const itens = ler()
            const alvo = itens.find((i) => i.id === item.id)
            if (alvo) {
                alvo.tentativas++
                alvo.ultimoErro = mensagemDoErro(e)
                // Só falha RESPONDIDA pelo servidor conta para esgotar: oito
                // janelas sem sinal são oito minutos num túnel, não um defeito.
                if (status !== 0) alvo.falhasNoServidor = (alvo.falhasNoServidor ?? 0) + 1
                if ((alvo.falhasNoServidor ?? 0) >= MAX_TENTATIVAS) {
                    // Esgotou: sai do topo (não trava o resto) e espera decisão
                    // humana — dinheiro e entrega não podem sumir em silêncio.
                    moverParaAtencao(alvo, `Não sincronizou após ${MAX_TENTATIVAS} tentativas. ${alvo.ultimoErro}`)
                    falharam++
                    continue
                }
                gravar(itens)
            }
            falharam++
            break
        }
    }

    return { enviados, falharam, restantes: quantidadePendente() }
}
