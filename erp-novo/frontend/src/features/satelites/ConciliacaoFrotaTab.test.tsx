import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { it, expect, vi, afterEach } from 'vitest'
import { api } from '@/lib/api'
import { ConciliacaoFrotaTab } from './ConciliacaoFrotaTab'

afterEach(() => vi.restoreAllMocks())

const mount = () => render(
  <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><ConciliacaoFrotaTab /></QueryClientProvider>,
)

const linhas = [
  { id: 1, placa: 'ABC-1D23', descricao: 'Caminhão 1', ativo: true, candidatos: [{ id: 50, placa: 'ABC1D23', descricao: 'Truck' }] },
  { id: 2, placa: 'XYZ9999', descricao: null, ativo: true, candidatos: [{ id: 60, placa: 'XYZ9999', descricao: 'A' }, { id: 61, placa: 'XYZ-9999', descricao: 'B' }] },
  { id: 3, placa: 'SEM0000', descricao: null, ativo: true, candidatos: [] },
]

it('mostra os três casos: um candidato, placa repetida e sem par', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: linhas } })
  mount()
  expect(await screen.findByText('Um candidato')).toBeVisible()
  expect(screen.getByText('Placa repetida na frota')).toBeVisible()
  expect(screen.getByText('Sem par na frota')).toBeVisible()
  // Ambíguo: os dois candidatos aparecem, e a escolha é de quem vê a tela.
  expect(screen.getAllByRole('button', { name: 'Vincular' })).toHaveLength(3)
})

it('vincula ao candidato ESCOLHIDO, e só depois do clique', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: linhas } })
  const put = vi.spyOn(api, 'put').mockResolvedValue({ data: { data: {} } })
  const user = userEvent.setup()
  mount()
  const linha = (await screen.findByText('XYZ-9999')).closest('li') as HTMLElement
  // Nada é vinculado ao abrir a tela, nem quando há um único candidato.
  expect(put).not.toHaveBeenCalled()
  await user.click(within(linha).getByRole('button', { name: 'Vincular' }))
  expect(put).toHaveBeenCalledWith('/monitora/veiculos/2', { placa: 'XYZ9999', veiculo_frota_id: 61 })
})

it('falha ao carregar não se apresenta como "tudo conciliado"', async () => {
  vi.spyOn(api, 'get').mockRejectedValue(new Error('Offline'))
  mount()
  expect(await screen.findByRole('alert')).toHaveTextContent('Offline')
  expect(screen.queryByText('Tudo conciliado')).not.toBeInTheDocument()
})
