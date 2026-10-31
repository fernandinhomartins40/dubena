import Http from "@/helpers/http"
import useAppStore from "@/store/appStore"
import {
    enfileirar,
    mensagemDoErro,
    novoId,
    pendentes,
    sincronizar,
    quantidadePendente,
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

/**
 * Tenta esvaziar a fila. Chamar quando o app volta ao primeiro plano e após
 * qualquer requisição bem-sucedida (sinal de que a rede voltou).
 */
export const sincronizarPendencias = () =>
    sincronizar((item) =>
        Http.client.request({
            url: item.endpoint,
            method: item.method,
            ...(item.data != null ? { data: item.data } : {}),
            // Mesma chave da primeira tentativa: é isso que evita a duplicata.
            headers: { "Idempotency-Key": item.id },
        }),
        useAppStore.getState().user?.id,
    )

export const temPendencias = (): boolean => quantidadePendente() > 0
