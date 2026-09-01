import { useEffect } from 'react'
import { useEmpresas } from '@/features/empresas/api'

/**
 * F9-06 — o título da aba carrega o nome de QUEM está logado.
 *
 * O `index.html` trazia `Dubena · ERP` — a primeira revenda, servida a todas.
 * Cada uma via a concorrente na aba do navegador, e num produto para N revendas
 * isso é branding alheio em cima do próprio trabalho.
 *
 * O HTML ficou neutro (`ERP`), porque no carregamento inicial ainda não se sabe
 * quem está logado; o nome entra aqui, quando a sessão já respondeu.
 *
 * ## Por que a empresa ATIVA e não a lista
 *
 * Numa rede de filiais a lista tem várias empresas, e o que identifica o
 * contexto é a ativa — a mesma que decide config, caixa e numeração fiscal. É
 * ela que o cabeçalho mostra, e a aba precisa concordar com o cabeçalho.
 */
export function useTituloDoTenant(): void {
  const { data: empresas } = useEmpresas()

  useEffect(() => {
    const ativa = empresas?.find((e) => e.ativa)
    const nome = ativa?.nome_informal || ativa?.razao_social

    // Sem empresa resolvida ainda: mantém o título neutro em vez de piscar um
    // nome errado. O default é da plataforma, e é explícito.
    document.title = nome ? `${nome} · ERP` : 'ERP'
  }, [empresas])
}
