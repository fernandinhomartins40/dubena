import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Central de Acessos (A2) — papéis e usuários da empresa ativa. */

// ---- Papéis (perfis) ----
export interface Papel {
  id: number
  nome: string
  descricao: string | null
  usuarios_count: number
  permissoes: string[]
}

/** Item do catálogo de permissões, agrupado por módulo na resposta. */
export interface PermissaoItem {
  chave: string
  descricao: string
}
export type CatalogoPermissoes = Record<string, PermissaoItem[]>

export const usePapeis = () =>
  useQuery<Papel[]>({ queryKey: ['papeis'], queryFn: async () => (await api.get('/papeis')).data.data })

export const useCatalogoPermissoes = () =>
  useQuery<CatalogoPermissoes>({
    queryKey: ['papeis', 'catalogo'],
    queryFn: async () => (await api.get('/papeis/catalogo')).data.data,
    staleTime: 5 * 60_000, // catálogo é estático (vem do código)
  })

export function useSalvarPapel() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) =>
      id ? (await api.put(`/papeis/${id}`, data)).data.data : (await api.post('/papeis', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['papeis'] }),
  })
}

export function useExcluirPapel() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/papeis/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['papeis'] }),
  })
}

// ---- Usuários ----
export interface UsuarioPapel {
  id: number
  nome: string
}
export interface Usuario {
  id: number
  name: string
  email: string
  ativo: boolean
  support: boolean
  papeis: UsuarioPapel[]
}

export const useUsuarios = () =>
  useQuery<Usuario[]>({ queryKey: ['usuarios'], queryFn: async () => (await api.get('/usuarios')).data.data })

export function useSalvarUsuario() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) =>
      id ? (await api.put(`/usuarios/${id}`, data)).data.data : (await api.post('/usuarios', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['usuarios'] }),
  })
}

export function useExcluirUsuario() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/usuarios/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['usuarios'] }),
  })
}

export function useResetarSenha() {
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Record<string, unknown> }) =>
      (await api.post(`/usuarios/${id}/resetar-senha`, data)).data,
  })
}
