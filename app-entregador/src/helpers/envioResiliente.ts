import Http from "@/helpers/http"
import useAppStore from "@/store/appStore"
import { UPLOAD_TIMEOUT_MS } from "@shared/http"
import { apagarArquivos, guardarArquivos, type ArquivoUpload } from "@/helpers/arquivosFila"
import {
    enfileirar,
    mensagemDoErro,
    novoId,
    pendentes,
    sincronizar,
    quantidadePendente,
    type FormularioFila,
    type ItemFila,
} from "@/helpers/filaOffline"

/**
 * Envio resiliente (F7) — tenta agora; se a rede não deixar, guarda para depois.
 *
 * **Por que aqui e não dentro do núcleo `@shared/http`.** Aquele núcleo é
 * compartilhado com o app do CLIENTE, onde enfileirar seria errado: o cliente
 * espera resposta na hora (pedido, pagamento) e uma ação que "acontece depois"
 * seria pior que uma que falha na cara dele. A fila é do trabalhador em rota.
 *
 * **A chave de idempotência viaja no cabeçalho.** Gerada no dispositivo e
 * preservada entre tentativas, ela é o que garante que o reenvio não duplique a
 * baixa do pedido — o middleware `Idempotente` do erp-novo devolve a resposta da
 * primeira execução em vez de repetir o efeito.
 */

/** Erros de transporte: sem resposta do servidor, ou 5xx. */
const eFalhaDeRede = (e: any): boolean => {
    const status = e?.status ?? e?.response?.status ?? 0

    return status === 0 || status >= 500 || status === 408 || status === 429
}

/**
 * Executa a escrita. Em falha de rede, enfileira e devolve `{ enfileirado: true }`
 * em vez de lançar — a tela mostra "será enviado quando houver sinal" e o
 * entregador segue trabalhando.
 */
export const enviarOuEnfileirar = async <T = any>(
    endpoint: string,
    method: ItemFila["method"],
    data: unknown = null,
): Promise<{ enfileirado: boolean; data?: T }> => {
    const chave = novoId()
    const dono = useAppStore.getState().user?.id

    // Ordem importa: "aceitar" que ficou na fila tem de chegar antes do "status"
    // feito depois que o sinal voltou (PIE P2-C-06). Esvazia primeiro; se ainda
    // sobrar algo deste entregador, a ação nova entra no fim da fila.
    if (pendentes().some((i) => i.dono === undefined || i.dono === dono)) {
        await sincronizarPendencias()
        if (pendentes().some((i) => i.dono === undefined || i.dono === dono)) {
            enfileirar(endpoint, method, data, chave, dono)
            return { enfileirado: true }
        }
    }

    try {
        const resposta = await Http.client.request<T>({
            url: endpoint,
            method,
            ...(data != null ? { data } : {}),
            headers: { "Idempotency-Key": chave },
        })

        return { enfileirado: false, data: resposta.data }
    } catch (e: any) {
        if (!eFalhaDeRede(e)) {
            // 4xx real (validação, permissão): é para o usuário ver AGORA.
            // Enfileirar esconderia um erro que nunca vai se resolver sozinho.
            // A tela mostra `e.message`: troca o "Request failed with status
            // code 422" do axios pela frase do servidor.
            if (e && typeof e === "object") e.message = mensagemDoErro(e, e.message)
            throw e
        }

        // Mesma chave da tentativa que falhou: ela pode ter chegado ao servidor.
        enfileirar(endpoint, method, data, chave, dono)

        return { enfileirado: true }
    }
}

/** Remonta o FormData a partir do que a fila guardou (texto + caminho dos anexos). */
export const montarFormulario = (f: FormularioFila): FormData => {
    const form = new FormData()
    for (const [campo, valor] of Object.entries(f.campos)) form.append(campo, valor)
    // O FormData do React Native aceita {uri, name, type} e lê o arquivo do disco
    // na hora do envio — por isso basta o caminho persistido.
    for (const [campo, arq] of Object.entries(f.arquivos)) form.append(campo, arq as any)
    return form
}

/** A mesma requisição, seja a 1ª tentativa ou o reenvio da fila. */
const requisicaoDo = (endpoint: string, method: ItemFila["method"], data: unknown, chave: string, formulario?: FormularioFila) =>
    formulario
        ? Http.client.request({
              url: endpoint,
              method,
              data: montarFormulario(formulario),
              timeout: UPLOAD_TIMEOUT_MS,
              headers: { "Idempotency-Key": chave, "Content-Type": "multipart/form-data" },
          })
        : Http.client.request({
              url: endpoint,
              method,
              ...(data != null ? { data } : {}),
              headers: { "Idempotency-Key": chave },
          })

/**
 * Versão multipart do `enviarOuEnfileirar` (P2-C-05): concluir entrega e
 * ocorrência, com foto e/ou assinatura.
 *
 * Antes, concluir exigia sinal e não levava chave: o 1º envio que estourava os
 * 20 s com a foto já tinha gravado no servidor, o entregador tocava de novo e
 * recebia 422 ("o pedido mudou de situação") — achava que a entrega não tinha
 * sido registrada — e cada reenvio deixava um arquivo órfão no disco.
 *
 * Ordem: (1) copia os anexos para fora do cache, ANTES da 1ª tentativa — se o
 * app morrer no meio do upload, a fila ainda tem o arquivo; (2) tenta com a
 * chave; (3) em falha de transporte, enfileira com a MESMA chave e os mesmos
 * campos (o servidor compara o conteúdo: campo diferente com a mesma chave é 409).
 */
export const enviarFormularioOuEnfileirar = async <T = any>(
    endpoint: string,
    campos: Record<string, string | number | null | undefined>,
    arquivos: Record<string, ArquivoUpload | null | undefined>,
): Promise<{ enfileirado: boolean; data?: T }> => {
    const chave = novoId()
    const dono = useAppStore.getState().user?.id

    const textos: Record<string, string> = {}
    for (const [k, v] of Object.entries(campos)) if (v != null && v !== "") textos[k] = String(v)

    let anexos: Record<string, ArquivoUpload>
    try {
        anexos = await guardarArquivos(chave, arquivos)
    } catch {
        // Sem como copiar (disco cheio?): segue com o arquivo onde está. Envia
        // agora do mesmo jeito; só a sobrevivência a um reinício fica menor.
        anexos = Object.fromEntries(Object.entries(arquivos).filter(([, a]) => a != null)) as Record<string, ArquivoUpload>
    }
    const formulario: FormularioFila = { campos: textos, arquivos: anexos }

    if (pendentes().some((i) => i.dono === undefined || i.dono === dono)) {
        await sincronizarPendencias()
        if (pendentes().some((i) => i.dono === undefined || i.dono === dono)) {
            enfileirar(endpoint, "POST", null, chave, dono, formulario)
            return { enfileirado: true }
        }
    }

    try {
        const resposta = await requisicaoDo(endpoint, "POST", null, chave, formulario)
        void apagarArquivos(chave)

        return { enfileirado: false, data: resposta.data as T }
    } catch (e: any) {
        if (!eFalhaDeRede(e)) {
            void apagarArquivos(chave)
            if (e && typeof e === "object") e.message = mensagemDoErro(e, e.message)
            throw e
        }
        enfileirar(endpoint, "POST", null, chave, dono, formulario)

        return { enfileirado: true }
    }
}

/**
 * Tenta esvaziar a fila. Chamar quando o app volta ao primeiro plano e após
 * qualquer requisição bem-sucedida (sinal de que a rede voltou).
 */
export const sincronizarPendencias = () =>
    sincronizar(
        // Mesma chave da primeira tentativa: é isso que evita a duplicata.
        (item) => requisicaoDo(item.endpoint, item.method, item.data, item.id, item.formulario),
        useAppStore.getState().user?.id,
    )

/** Há ação deste pedido esperando sinal? (ex.: conclusão guardada offline) */
export const acaoPendenteDoPedido = (pedidoId: number, acao: "concluir" | "ocorrencia" | "recusar" | "aceitar"): boolean =>
    pendentes().some((i) => i.endpoint === `app/v1/entregador/pedidos/${pedidoId}/${acao}`)

export const temPendencias = (): boolean => quantidadePendente() > 0
