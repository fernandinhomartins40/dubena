import { act, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { SorteioPage } from '../SorteioPage'

/**
 * O sorteio continua exigindo confirmação — agora é o palco. Abrir não sorteia;
 * só "Iniciar sorteio" chama o servidor. Se falhar, nada foi registrado e o
 * palco volta ao preparo com o botão disponível.
 */
const lista = [{ id: 1, descricao: 'Campanha de Natal', premio: 'Um botijão por mês', situacao: 'aberto', numero_sorteado: null, numeros_count: 3, data_sorteio: null }]
const palco = {
  sorteio: { id: 1, descricao: 'Campanha de Natal', premio: 'Um botijão por mês', data_sorteio: '2026-12-24', situacao: 'aberto' },
  participantes: [{ numero: '0001', nome: 'Ana' }, { numero: '0002', nome: 'Bruno' }, { numero: '0003', nome: 'Maria' }],
  resultado: null,
}
const resultado = {
  numero: '0003',
  cliente: { id: 9, nome: 'Maria Aparecida Souza', bairro: 'Centro', cidade: 'Guarapuava', uf: 'PR', telefone: '(42) •••••-4567' },
  sorteado_em: '2026-12-24T20:00:00-03:00', sorteado_por: 'Vilso', participantes: 3,
}

function montar() {
  vi.spyOn(api, 'get').mockImplementation(async (url: string) => ({ data: { data: url.endsWith('/palco') ? palco : lista } }) as never)
  render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })}>
      <SorteioPage />
    </QueryClientProvider>,
  )
}

// jsdom não desenha em canvas; o confete trata contexto nulo como 'sem efeito'.
HTMLCanvasElement.prototype.getContext = (() => null) as never

afterEach(() => {
  vi.restoreAllMocks()
  vi.useRealTimers()
})

describe('palco do sorteio', () => {
  it('abrir o palco não sorteia; falha mantém o contexto e nada é registrado', async () => {
    montar()
    const post = vi.spyOn(api, 'post').mockRejectedValue({ response: { data: { message: 'Servidor fora do ar.' } } })
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: /Sortear/ }))
    expect(await screen.findByRole('heading', { name: 'Campanha de Natal' })).toBeInTheDocument()
    expect(within(screen.getByRole('dialog', { name: 'Palco do sorteio' })).getByText('Um botijão por mês')).toBeInTheDocument()
    expect(post).not.toHaveBeenCalled()

    await user.click(screen.getByRole('button', { name: /Iniciar sorteio/ }))
    await waitFor(() => expect(post).toHaveBeenCalledWith('/sorteios/1/sortear'))
    expect(await screen.findByRole('alert')).toHaveTextContent('Servidor fora do ar.')
    expect(screen.getByRole('button', { name: /Iniciar sorteio/ })).toBeEnabled()
  })

  it('contagem, roleta e revelação terminam no ganhador registrado pelo servidor', async () => {
    // Relógio falso desde o início, mas andando: as buscas assíncronas seguem
    // funcionando, e a cena de ~13 s é pulada com advanceTimersByTime.
    vi.useFakeTimers({ shouldAdvanceTime: true })
    montar()
    const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: resultado } })
    const user = userEvent.setup({ advanceTimers: (ms) => vi.advanceTimersByTime(ms) })

    await user.click(await screen.findByRole('button', { name: /Sortear/ }))
    await screen.findByRole('heading', { name: 'Campanha de Natal' })
    await user.click(screen.getByRole('button', { name: /Iniciar sorteio/ }))
    expect(post).toHaveBeenCalledTimes(1)

    // Contagem regressiva à vista antes de qualquer número.
    await act(async () => { vi.advanceTimersByTime(10) })
    expect(screen.getByLabelText('Faltam 5')).toBeInTheDocument()

    // Contagem (5 s) + roleta (~7 s) + pausa: tudo isto e sobra.
    await act(async () => { vi.advanceTimersByTime(16_000) })

    expect(screen.getByText('Temos um ganhador!')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Maria Aparecida Souza' })).toBeInTheDocument()
    expect(screen.getByText('Centro · Guarapuava/PR')).toBeInTheDocument()
    expect(screen.getByText(/0003/)).toBeInTheDocument()
    // Sorteou uma vez só, apesar de toda a encenação.
    expect(post).toHaveBeenCalledTimes(1)
  })
})
