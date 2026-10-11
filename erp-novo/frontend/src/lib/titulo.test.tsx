import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { afterEach, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { useNomeDoTenant } from './titulo'

function Marca() {
  return <span>{useNomeDoTenant() ?? 'carregando'}</span>
}

function montar() {
  render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><Marca /></QueryClientProvider>)
}

afterEach(() => vi.restoreAllMocks())

// O menu dizia "Dubena" para todas as revendas; agora diz quem está logado.
it('a marca do menu é a empresa ATIVA, pelo nome informal', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: [
    { id: 1, razao_social: 'Outra Revenda Ltda', nome_informal: 'Outra', ativa: false },
    { id: 2, razao_social: 'Gás Bom Ltda', nome_informal: 'Gás Bom', ativa: true },
  ] } })
  montar()
  expect(await screen.findByText('Gás Bom')).toBeInTheDocument()
})

it('sem nome informal, usa a razão social; enquanto carrega, não inventa nome', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: [{ id: 2, razao_social: 'Gás Bom Ltda', nome_informal: null, ativa: true }] } })
  montar()
  expect(screen.getByText('carregando')).toBeInTheDocument()
  expect(await screen.findByText('Gás Bom Ltda')).toBeInTheDocument()
})
