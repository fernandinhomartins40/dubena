import { api } from './api'

/**
 * Abre um PDF da API numa aba nova, para impressão.
 *
 * Vai por blob e não por `window.open` na URL porque o Bearer token viaja no
 * header: um link direto chegaria sem autenticação e o navegador mostraria 401.
 *
 * Abre em vez de baixar porque estes documentos existem para virar papel — o
 * operador imprime na hora (DANFE que acompanha a carga, vale que o cliente
 * leva, contrato que ele assina).
 */
export async function abrirPdf(url: string): Promise<void> {
  const resp = await api.get(url, { responseType: 'blob' })
  const objectUrl = URL.createObjectURL(resp.data as Blob)
  window.open(objectUrl, '_blank', 'noopener')
  // Revoga tarde: revogar antes de a aba ler o blob mostra página em branco.
  setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
}

/**
 * Lê a mensagem de erro que a API devolveu quando a resposta era `blob`.
 *
 * Com `responseType: 'blob'` o corpo do erro também vem como blob — sem
 * desempacotar, o motivo da recusa ("nota não autorizada", "vale cancelado")
 * nunca chegaria à tela, e o usuário veria só um erro genérico.
 */
export async function mensagemDeErroBlob(e: unknown, padrao: string): Promise<string> {
  try {
    const data = (e as { response?: { data?: Blob } })?.response?.data
    if (!data) return padrao
    return JSON.parse(await data.text())?.message ?? padrao
  } catch {
    return padrao
  }
}
