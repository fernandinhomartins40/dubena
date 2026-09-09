import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'

export type FormatoExportacao = 'csv' | 'xlsx' | 'pdf'

export interface CampoExportavel {
  chave: string
  rotulo: string
  grupo: string
  /** Dado pessoal sensível: nunca vem pré-marcado e o modal avisa. */
  sensivel: boolean
}

export interface OpcoesExportacao {
  campos: CampoExportavel[]
  padrao: string[]
  limite_pdf: number
  xlsx_lento_acima_de: number
}

/** Filtros de QUAIS clientes entram. Tudo opcional = não restringe. */
export interface FiltrosExportacao {
  situacao?: 'ativos' | 'inativos' | 'todos'
  q?: string
  rua_ids?: number[]
  bairro_ids?: number[]
  cidade_ids?: number[]
  uf?: string
  cep?: string
  sem_geolocalizacao?: boolean
  cliente?: boolean
  fornecedor?: boolean
  transportador?: boolean
  convenio?: boolean
  convenio_ativo?: boolean
  gasdopovo?: boolean
  nfemite?: boolean
  simples?: boolean
  segmento_ids?: number[]
  tipopessoa_ids?: number[]
  cadastro_inicio?: string
  cadastro_fim?: string
  ultima_compra_inicio?: string
  ultima_compra_fim?: string
  sem_compra_dias?: number
  aniversario_mes?: number
  com_credito?: boolean
  com_saldo_devedor?: boolean
}

/**
 * O catálogo de campos vem do backend, não hardcoded aqui: é o mesmo array que
 * autoriza a exportação, então tela e enforcement não divergem.
 */
export function useOpcoesExportacao(habilitado: boolean) {
  return useQuery<OpcoesExportacao>({
    queryKey: ['cliente-exportacao-opcoes'],
    queryFn: async () => (await api.get('/clientes/exportacao/opcoes')).data.data,
    enabled: habilitado,
    staleTime: Infinity, // catálogo estático: não muda entre aberturas do modal
  })
}

export interface PreviaExportacao {
  total: number
  excede_pdf: boolean
}

/** Quantos clientes o filtro atinge — mostrado ANTES de gerar o arquivo. */
export function usePreviaExportacao(filtros: FiltrosExportacao, habilitado: boolean) {
  return useQuery<PreviaExportacao>({
    queryKey: ['cliente-exportacao-previa', filtros],
    queryFn: async () => (await api.get('/clientes/exportacao/previa', { params: limpar(filtros) })).data.data,
    enabled: habilitado,
    placeholderData: (prev) => prev,
  })
}

/**
 * Baixa o arquivo. Não é useMutation de propósito: a resposta é um blob, não
 * cache — e o Bearer viaja no header, então link direto chegaria sem auth.
 */
export async function baixarExportacao(
  formato: FormatoExportacao,
  campos: string[],
  filtros: FiltrosExportacao,
): Promise<void> {
  const resp = await api.post(
    '/clientes/exportacao',
    { formato, campos, ...limpar(filtros) },
    {
      responseType: 'blob',
      // Um XLSX de 50 mil linhas leva ~100s para o PhpSpreadsheet montar e
      // gravar (medido). O nginx corta em 300s, então damos a mesma folga:
      // sem isso, um default menor abortaria no cliente um arquivo que o
      // servidor ainda estava gerando.
      timeout: 300_000,
    },
  )

  const nome = nomeDoHeader(resp.headers?.['content-disposition'])
    ?? `clientes-${new Date().toISOString().slice(0, 10)}.${formato}`

  const url = URL.createObjectURL(resp.data as Blob)
  const a = document.createElement('a')
  a.href = url
  a.download = nome
  a.click()
  URL.revokeObjectURL(url)
}

function nomeDoHeader(header?: string): string | null {
  return header?.match(/filename="?([^"]+)"?/)?.[1] ?? null
}

/**
 * Remove chaves vazias antes de enviar.
 *
 * Sem isso um `uf: ''` chegaria ao backend como filtro real e a exportação
 * voltaria vazia — o pior defeito possível aqui, porque um arquivo vazio parece
 * "não há clientes assim" em vez de "o filtro estava quebrado".
 */
function limpar(f: FiltrosExportacao): Record<string, unknown> {
  return Object.fromEntries(
    Object.entries(f).filter(([, v]) =>
      v !== undefined && v !== null && v !== '' && !(Array.isArray(v) && v.length === 0)),
  )
}
