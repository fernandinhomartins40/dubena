import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { it, expect, vi, afterEach } from 'vitest'
import { api } from '@/lib/api'
import { DRETab } from './FinanceiroExtraTabs'
afterEach(() => vi.restoreAllMocks())
it('só aplica o novo período na consulta explícita e permite repetir a consulta', async () => {
  const get = vi.spyOn(api, 'get').mockResolvedValue({ data: { data: { receitas: [], despesas: [], total_receitas: 0, total_despesas: 0, resultado: 0 } } })
  render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><DRETab /></QueryClientProvider>)
  const user = userEvent.setup()
  fireEvent.change(screen.getByLabelText('Início'), { target: { value: '2026-01-01' } })
  fireEvent.change(screen.getByLabelText('Fim'), { target: { value: '2026-01-31' } })
  await user.click(screen.getByRole('button', { name: 'Gerar DRE' }))
  await waitFor(() => expect(get).toHaveBeenCalledTimes(1))
  fireEvent.change(screen.getByLabelText('Fim'), { target: { value: '2026-02-28' } })
  expect(get).toHaveBeenCalledTimes(1)
  await user.click(screen.getByRole('button', { name: 'Gerar DRE' }))
  await waitFor(() => expect(get).toHaveBeenCalledTimes(2))
  await user.click(screen.getByRole('button', { name: 'Gerar DRE' }))
  await waitFor(() => expect(get).toHaveBeenCalledTimes(3))
})
