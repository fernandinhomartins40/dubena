/**
 * Quando o rastreamento em 2º plano tem de parar sozinho (PIE P2-C-07).
 *
 * Arquivo PURO (sem expo) para ser testado em node; quem aplica é a task de
 * `backgroundLocation.ts` e o loop de `useRastreamento.ts`.
 *
 * Antes: com o app fora da memória, a task rodava sem a store hidratada, mandava
 * ping sem token (401 a cada 15 s, bateria) e nunca desligava; se a Central
 * encerrava a jornada, o GPS do aparelho seguia ligado até alguém abrir o
 * Início — contra a política de privacidade, que promete parar no fim da jornada.
 */

/** Sem token ou fora de serviço não há motivo para coletar posição. */
export const deveRastrear = (s: { apiToken?: string | null; emServico?: boolean }): boolean =>
    Boolean(s.apiToken) && Boolean(s.emServico)

/**
 * Resposta do servidor que encerra a coleta:
 *  - 401: sessão vencida (o núcleo já tentou renovar);
 *  - 403: o token não é mais de entregador;
 *  - 422: "Inicie a jornada para operar." — a jornada acabou no servidor.
 * Falta de sinal (status 0) e 5xx NÃO param: o próximo ping tenta de novo.
 */
export const deveEncerrarRastreamento = (e: unknown): boolean => {
    const status = (e as any)?.status ?? (e as any)?.response?.status ?? 0
    return status === 401 || status === 403 || status === 422
}
