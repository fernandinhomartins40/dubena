import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { ProdutoFormPage } from './ProdutoFormPage'

vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: () => true, hasFeature: () => true }) }))

// O que a API devolve (nomes das COLUNAS) — dados fiscais de um GLP migrado.
const salvo = {
  id: 5, descricao: 'GLP P13', ativo: true, tipo: 'CONTEUDO', vasilhame_retornavel: true, dias_giro: 15,
  nfe_permite: true, nfe_cest: '0601000', ncm: '27111910', ean_trib: '7891234567890',
  grupo_fiscal_id: 1, grupofiscal_label: 'NCM 27111910', nfe_desc_anp: 'GLP', nfe_cprod_anp: '210203001', pgnn: 0, pglp: 100,
  preco_venda: 120, origens: [{ id: 9, uf: 'PR', ind_import: 0, cuf_orig: 41, p_orig: 100 }],
}

afterEach(() => vi.restoreAllMocks())

/**
 * O formulário lia e gravava com nomes antigos (`nfcest`, `vasilhameretornavel`,
 * `indimport`): abria um GLP com CEST, ANP e "vasilhame retornável" em branco,
 * e o servidor descartava o que voltava. Abrir e salvar sem tocar em nada tem
 * de devolver os MESMOS dados.
 */
it('abrir e salvar um produto devolve os dados fiscais intactos', async () => {
  vi.spyOn(api, 'get').mockImplementation(async (url: string) => ({ data: { data: url === '/produtos/5' ? salvo : [] } }) as never)
  const put = vi.spyOn(api, 'put').mockResolvedValue({ data: { data: { id: 5 } } })
  const user = userEvent.setup()
  render(
    <MemoryRouter initialEntries={['/produtos/5']}>
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <Routes><Route path="/produtos/:id" element={<ProdutoFormPage />} /></Routes>
      </QueryClientProvider>
    </MemoryRouter>,
  )

  await screen.findByDisplayValue('GLP P13')
  await user.click(screen.getByRole('button', { name: /Salvar/ }))

  await waitFor(() => expect(put).toHaveBeenCalled())
  const enviado = put.mock.calls[0][1] as Record<string, any>
  expect(enviado).toMatchObject({
    vasilhame_retornavel: true, dias_giro: 15, nfe_permite: true, nfe_cest: '0601000', ean_trib: '7891234567890',
    grupo_fiscal_id: 1, nfe_desc_anp: 'GLP', nfe_cprod_anp: '210203001', preco_venda: 120,
  })
  expect(enviado.origens[0]).toMatchObject({ uf: 'PR', ind_import: 0, cuf_orig: 41, p_orig: 100 })
  // Nenhum nome antigo vai junto.
  for (const antigo of ['nfcest', 'vasilhameretornavel', 'diasgiro', 'nfgrupofiscal_id', 'precovenda']) expect(enviado).not.toHaveProperty(antigo)
})
