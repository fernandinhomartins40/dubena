import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Pagamentos (C4): cartões (NSU) e Gás do Povo. */

// ---- Cartões ----
export interface CartaoTransacao {
  id: number; bandeira: string | null; tipo: string; nsu: string | null; parcelas: number
  valor_bruto: string; taxa_percentual: string; valor_liquido: string; situacao: string
}
export const useCartoes = () =>
  useQuery<CartaoTransacao[]>({ queryKey: ['cartoes'], queryFn: async () => (await api.get('/cartoes')).data.data })
export function useRegistrarCartao() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.post('/cartoes', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['cartoes'] }),
  })
}

// ---- Gás do Povo ----
export interface Beneficio {
  id: number; cliente_id: number | null; nis: string | null; competencia: string
  valor: string; situacao: string; pedido_id: number | null
}
export const useBeneficios = () =>
  useQuery<Beneficio[]>({ queryKey: ['gasdopovo'], queryFn: async () => (await api.get('/gasdopovo')).data.data })
export function useRegistrarBeneficio() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.post('/gasdopovo', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['gasdopovo'] }),
  })
}
export function useSacarBeneficio() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Record<string, unknown> }) => (await api.post(`/gasdopovo/${id}/sacar`, data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['gasdopovo'] }),
  })
}

// ---- Gás do Povo: o PROGRAMA (como o legado opera) ----
//
// Distinto dos benefícios acima (modelo de voucher): aqui é o programa em si —
// parâmetros da empresa, quem são os beneficiários e o que foi vendido
// subsidiado. Ver docs/02-auditoria-legado/GAS_DO_POVO_NO_LEGADO.md.

export interface GpParametros {
  configurado: boolean
  produto_id: number | null; produto: string | null
  preco: number | null; preco_venda: number | null
  condicaopagamento_id: number | null; condicaopagamento: string | null
  condicaopagamento_frete_id: number | null; condicaopagamento_frete: string | null
  valor_frete: number | null; ccfrete_id: number | null; pcfrete_id: number | null
}
export interface GpResumo {
  pedidos: number; valor: number; botijoes: number
  ticket_medio: number; preco_medio: number | null; beneficiarios: number
}
export interface GpMes { mes: string; pedidos: number; valor: number }

export interface GpPrograma {
  parametros: GpParametros
  resumo: GpResumo
  por_mes: GpMes[]
}

export function usePrograma(de?: string, ate?: string) {
  return useQuery<GpPrograma>({
    queryKey: ['gp-programa', de, ate],
    queryFn: async () => (await api.get('/gasdopovo/programa', { params: { de, ate } })).data.data,
  })
}

export interface GpBeneficiario {
  id: number; nome: string; cpf: string | null; cnpj: string | null
  ativo: boolean | number | null; data_ultima_compra: string | null
}

export function useBeneficiarios(q: string, page: number) {
  return useQuery<{ data: GpBeneficiario[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }>({
    queryKey: ['gp-beneficiarios', q, page],
    queryFn: async () => (await api.get('/gasdopovo/beneficiarios', { params: { q, page } })).data,
    placeholderData: (prev) => prev,
  })
}

export interface GpVenda {
  id: number; datahora: string | null; cliente: string | null
  situacao: string | null; valorvenda: number
}

export function useVendasGp(de?: string, ate?: string, page = 1) {
  return useQuery<{ data: GpVenda[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }>({
    queryKey: ['gp-vendas', de, ate, page],
    queryFn: async () => (await api.get('/gasdopovo/vendas', { params: { de, ate, page } })).data,
    placeholderData: (prev) => prev,
  })
}
