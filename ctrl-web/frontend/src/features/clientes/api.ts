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
  nome: string
  fantasia?: string | null
  numero: string
  cidade_id: number | null
  bairro_id?: number | null
  uf?: string | null
  cep?: string | null
  email?: string | null
  cpf?: string | null
  cnpj?: string | null
  sexo?: string | null
  tipopessoa_id?: number | null
  segmento_id?: number | null
  observacoes?: string | null
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
