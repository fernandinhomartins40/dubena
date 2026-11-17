/**
 * R2-13 — `Idempotency-Key` no POST do pedido.
 *
 * O servidor já aceita a chave (`middleware('idempotente')` em `POST
 * app/v1/pedidos`): com ela, a segunda tentativa devolve o pedido que a primeira
 * criou em vez de criar outro. O app nunca a mandava — um "Finalizar" tocado de
 * novo depois de um timeout podia virar dois pedidos (ou, com a regra de "1
 * pendente", um 422 confuso para quem acabou de comprar).
 *
 * O núcleo HTTP compartilhado (`@shared/http`, do app do entregador também) não
 * recebe cabeçalho por chamada. Em vez de duplicar a normalização de erro dele
 * aqui, a chave é registrada pelo CONTEÚDO do corpo e um interceptor a promove a
 * cabeçalho quando aquele corpo sai.
 *
 * Por que não um Symbol no próprio payload (a 1ª versão): o axios clona o `data`
 * no `mergeConfig` antes dos interceptores, e o clone perde a chave-símbolo. O
 * teste de serviço (`order.service.test.ts`) pegou isso — o cabeçalho não saía.
 *
 * Quando o núcleo aceitar `headers` por chamada, este arquivo vira uma linha.
 */
const pendentes = new Map<string, string>()

export function comChaveIdempotente<T extends object>(payload: T, chave: string): T {
    pendentes.set(JSON.stringify(payload), chave)
    return payload
}

/** Só o que o interceptor usa — o núcleo tem o próprio axios, de outra versão. */
interface ComInterceptor {
    interceptors: { request: { use: (fn: (config: any) => any) => number } }
}

const instalados = new WeakSet<ComInterceptor>()

export function instalarIdempotencia(client: ComInterceptor): void {
    if (instalados.has(client)) return
    instalados.add(client)
    client.interceptors.request.use((config: any) => {
        if (config.data && typeof config.data === "object") {
            const corpo = JSON.stringify(config.data)
            const chave = pendentes.get(corpo)
            if (chave && config.headers) {
                pendentes.delete(corpo)
                if (typeof config.headers.set === "function") config.headers.set("Idempotency-Key", chave)
                else config.headers["Idempotency-Key"] = chave
            }
        }
        return config
    })
}
