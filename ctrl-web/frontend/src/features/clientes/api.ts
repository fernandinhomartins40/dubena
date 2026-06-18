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
