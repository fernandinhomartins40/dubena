import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

export interface ClienteListItem {
  id: number
  nome: string
  fantasia: string | null
  cpf: string | null
  cnpj: string | null
  email: string | null
  uf: string | null
  cliente: number
  fornecedor: number
  ativo: number | null
}

export interface ClienteForm {
  // Dados gerais
  nome: string
  fantasia?: string | null
  tipopessoa_id?: number | null
  segmento_id?: number | null
  sexo?: string | null
  datanascimento?: string | null
  observacoes?: string | null
  // Fiscal / documentos
  cpf?: string | null
  rg?: string | null
  cnpj?: string | null
  inscricao_estadual?: string | null
  indicador_ie?: number | null
  suframa?: string | null
  consisa_id?: string | null
  // Flags
  cliente?: boolean
  fornecedor?: boolean
  transportador?: boolean
  simples?: boolean
  ativo?: boolean
  nfemite?: boolean
  gasdopovo?: boolean
  // Endereço
  numero: string
  cidade_id: number | null
  bairro_id?: number | null
  rua_id?: number | null
  uf?: string | null
  cep?: string | null
  complemento?: string | null
  ponto_referencia?: string | null
  email?: string | null
}

interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export function useClientes(q: string, page: number) {
  return useQuery<Paginated<ClienteListItem>>({
    queryKey: ['clientes', q, page],
    queryFn: async () => (await api.get('/clientes', { params: { q, page } })).data,
    placeholderData: (prev) => prev,
  })
}

export function useCliente(id: number | null) {
  return useQuery({
    queryKey: ['cliente', id],
    queryFn: async () => (await api.get(`/clientes/${id}`)).data.data,
    enabled: id !== null,
  })
}

export interface Telefone {
  id: number
  telefone: string
  whatsapp: number
  telefonetipo_id: number
}

export function useTelefones(clienteId: number | null) {
  return useQuery<Telefone[]>({
    queryKey: ['cliente-telefones', clienteId],
    queryFn: async () => (await api.get(`/clientes/${clienteId}/telefones`)).data.data,
    enabled: clienteId !== null,
  })
}

export function useAddTelefone(clienteId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: { telefone: string; whatsapp: boolean; telefonetipo_id: number }) =>
      (await api.post(`/clientes/${clienteId}/telefones`, data)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['cliente-telefones', clienteId] }),
  })
}

export function useDelTelefone(clienteId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (telId: number) => (await api.delete(`/clientes/${clienteId}/telefones/${telId}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['cliente-telefones', clienteId] }),
  })
}

// ---- Sub-recursos (abas Histórico/Interações/Convênio/Preços) ----
export function useHistorico(clienteId: number) {
  return useQuery({ queryKey: ['cli-historico', clienteId], queryFn: async () => (await api.get(`/clientes/${clienteId}/historico`)).data.data })
}
export function useInteracoes(clienteId: number) {
  return useQuery({ queryKey: ['cli-interacoes', clienteId], queryFn: async () => (await api.get(`/clientes/${clienteId}/interacoes`)).data.data })
}
export function useAddInteracao(clienteId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (d: { tipo_id: number; situacao_id: number; descricao: string; acao?: string }) => (await api.post(`/clientes/${clienteId}/interacoes`, d)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['cli-interacoes', clienteId] }),
  })
}
export function useDelInteracao(clienteId: number) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (subId: number) => (await api.delete(`/clientes/${clienteId}/interacoes/${subId}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['cli-interacoes', clienteId] }) })
}
export function useConvenio(clienteId: number) {
  return useQuery({ queryKey: ['cli-convenio', clienteId], queryFn: async () => (await api.get(`/clientes/${clienteId}/convenio`)).data })
}
export function useSalvarConvenio(clienteId: number) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (d: Record<string, unknown>) => (await api.put(`/clientes/${clienteId}/convenio`, d)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['cli-convenio', clienteId] }) })
}
export function useAddDependente(clienteId: number) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (d: { nome: string; parentesco_id: number; ativo?: boolean }) => (await api.post(`/clientes/${clienteId}/convenio/dependentes`, d)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['cli-convenio', clienteId] }) })
}
export function useDelDependente(clienteId: number) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (depId: number) => (await api.delete(`/clientes/${clienteId}/convenio/dependentes/${depId}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['cli-convenio', clienteId] }) })
}
export function usePrecos(clienteId: number) {
  return useQuery({ queryKey: ['cli-precos', clienteId], queryFn: async () => (await api.get(`/clientes/${clienteId}/precos`)).data.data })
}

export function useExcluirCliente() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/clientes/${id}`)).data,
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['clientes'] }); qc.invalidateQueries({ queryKey: ['dashboard-resumo'] }) },
  })
}

export function useSalvarCliente() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: ClienteForm }) => {
      if (id) return (await api.put(`/clientes/${id}`, data)).data.data
      return (await api.post('/clientes', data)).data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['clientes'] })
      qc.invalidateQueries({ queryKey: ['dashboard-resumo'] })
    },
  })
}
