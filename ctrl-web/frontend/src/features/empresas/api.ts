import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

export interface EmpresaListItem {
  id: number
  nome_informal: string | null
  razao_social: string
  cnpj: string | null
  uf: string | null
  ativo: boolean | number
  matriz: boolean | number
  grupo_id: number
  ativa: boolean
}

export function useEmpresas() {
  return useQuery<EmpresaListItem[]>({
    queryKey: ['empresas'],
    queryFn: async () => (await api.get('/empresas')).data.data,
  })
}

export function useEmpresa(id: number | null) {
  return useQuery({
    queryKey: ['empresa', id],
    queryFn: async () => (await api.get(`/empresas/${id}`)).data.data,
    enabled: id !== null,
  })
}

export function useSalvarEmpresa() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) =>
      id ? (await api.put(`/empresas/${id}`, data)).data.data : (await api.post('/empresas', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['empresas'] }),
  })
}

export function useExcluirEmpresa() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/empresas/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['empresas'] }),
  })
}

export function useAtivarEmpresa() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.post(`/empresas/${id}/ativar`)).data,
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['empresas'] }); qc.invalidateQueries({ queryKey: ['me'] }) },
  })
}

// ---- Config (106 col) ----
export function useEmpresaConfig(empresaId: number | null) {
  return useQuery({
    queryKey: ['empresa-config', empresaId],
    queryFn: async () => (await api.get(`/empresas/${empresaId}/config`)).data.data,
    enabled: empresaId !== null,
  })
}
export function useSalvarConfig(empresaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.put(`/empresas/${empresaId}/config`, data)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['empresa-config', empresaId] }),
  })
}
export function useSenhaMestra(empresaId: number) {
  return useMutation({
    mutationFn: async (data: { senha_atual?: string; senha_nova: string }) => (await api.put(`/empresas/${empresaId}/config/senha-mestra`, data)).data,
  })
}
export function useTestarEmail(empresaId: number) {
  return useMutation({
    mutationFn: async (data: { to: string; subject?: string; content?: string }) => (await api.post(`/empresas/${empresaId}/config/testar-email`, data)).data,
  })
}

// ---- Grupos ----
export interface GrupoItem { id: number; descricao: string; ativo: number }
export function useGrupos() {
  return useQuery<GrupoItem[]>({ queryKey: ['grupos'], queryFn: async () => (await api.get('/grupos')).data.data })
}
export function useSalvarGrupo() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...data }: { id?: number; descricao: string; ativo?: boolean }) =>
      id ? (await api.put(`/grupos/${id}`, data)).data : (await api.post('/grupos', data)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['grupos'] }),
  })
}
export function useExcluirGrupo() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/grupos/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['grupos'] }) })
}
