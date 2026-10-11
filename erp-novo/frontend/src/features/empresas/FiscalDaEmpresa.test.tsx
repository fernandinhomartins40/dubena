import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { afterEach, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { FiscalCampos, useFiscalDaEmpresa } from './FiscalDaEmpresa'

const fiscal = {
  ambiente: 2, regime_tributario: 3, serie_nfe: 1, serie_nfce: 1, csc_id: '000002', tem_csc: true,
  ultimo_numero_nfe: 335358, ultimo_numero_nfce: 361778, nfe_emite: true, nfce_emite: true, sped_emite: false,
  contador: {}, cadastro: { cnae: '4784900' },
}

function Tela() {
  const ctl = useFiscalDaEmpresa(2)
  return <><FiscalCampos ctl={ctl} /><button onClick={() => { void ctl.salvar(2) }}>salvar</button></>
}

afterEach(() => vi.restoreAllMocks())

it('mostra o que a emissão usa e salva só o que mudou, sem reenviar o CSC', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: fiscal } })
  const put = vi.spyOn(api, 'put').mockResolvedValue({ data: { data: fiscal } })
  const user = userEvent.setup()
  render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><Tela /></QueryClientProvider>)

  // A numeração aparece, mas só para leitura.
  expect(await screen.findByText('Último número emitido: 361778')).toBeInTheDocument()
  expect(screen.getByText(/Em homologação as notas são de teste/)).toBeInTheDocument()

  const serieNfce = screen.getByRole('spinbutton', { name: 'Série NFC-e' })
  await user.clear(serieNfce)
  await user.type(serieNfce, '2')
  await user.click(screen.getByRole('button', { name: 'salvar' }))

  await waitFor(() => expect(put).toHaveBeenCalledWith('/empresas/2/fiscal', { serie_nfce: 2 }))
})
