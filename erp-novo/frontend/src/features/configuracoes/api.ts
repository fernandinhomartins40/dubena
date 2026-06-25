import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Config global do grupo (F01): segredos voltam como flags "*_definido". */
export interface ConfigGlobal {
  rt_cnpj: string | null
  rt_contato: string | null
  rt_email: string | null
  rt_telefone: string | null
  rt_id_csrt: string | null
  rt_csrt_definido: boolean
  email_remetente: string | null
  email_nome_remetente: string | null
  email_host: string | null
  email_porta: number | null
  email_usuario: string | null
  email_senha_definida: boolean
  email_tls: boolean
  sat_cnpj_prod: string | null
  sat_cnpj_homolog: string | null
  sat_signac_prod_definido: boolean
  sat_signac_homolog_definido: boolean
  google_maps_key: string | null
  link_monitoramento: string | null
}

export function useConfigGlobal() {
  return useQuery<ConfigGlobal>({
    queryKey: ['config-global'],
    queryFn: async () => (await api.get('/config-global')).data.data,
  })
}

export function useSalvarConfigGlobal() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Partial<Record<string, unknown>>) =>
      (await api.put('/config-global', data)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['config-global'] }),
  })
}
