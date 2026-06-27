import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'

// ---- Satélites status (painel agregador) ----
export const useRelatorios = () => useQuery<any[]>({ queryKey: ['sat-rel'], queryFn: async () => (await api.get('/satelites/relatorios')).data.data })
export const useMonitoramento = () => useQuery({ queryKey: ['sat-mon'], queryFn: async () => (await api.get('/satelites/monitoramento')).data.data })
export const useIntegracoes = () => useQuery({ queryKey: ['sat-int'], queryFn: async () => (await api.get('/satelites/integracoes')).data.data })
