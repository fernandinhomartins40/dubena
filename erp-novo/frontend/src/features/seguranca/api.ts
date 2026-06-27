import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Segurança da conta (A5): 2FA (TOTP) e sessões ativas. */

export interface TwoFactorStatus {
  habilitado: boolean
  pendente: boolean
}

export const useTwoFactorStatus = () =>
  useQuery<TwoFactorStatus>({
    queryKey: ['2fa', 'status'],
    queryFn: async () => (await api.get('/seguranca/2fa')).data,
  })

export function useTwoFactorSetup() {
  return useMutation({
    mutationFn: async () => (await api.post('/seguranca/2fa/setup')).data as { secret: string; otpauth_uri: string },
  })
}

export function useTwoFactorConfirm() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (otp: string) => (await api.post('/seguranca/2fa/confirmar', { otp })).data as { recovery_codes: string[] },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['2fa', 'status'] }),
  })
}

export function useTwoFactorDisable() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (password: string) => (await api.post('/seguranca/2fa/desabilitar', { password })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['2fa', 'status'] }),
  })
}

export interface Sessao {
  id: number
  name: string
  last_used_at: string | null
  created_at: string | null
  atual: boolean
}

export const useSessoes = () =>
  useQuery<Sessao[]>({
    queryKey: ['sessoes'],
    queryFn: async () => (await api.get('/seguranca/sessoes')).data.data,
  })

export function useRevogarSessao() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/seguranca/sessoes/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['sessoes'] }),
  })
}

export function useRevogarOutras() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => (await api.post('/seguranca/sessoes/revogar-outras')).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['sessoes'] }),
  })
}
