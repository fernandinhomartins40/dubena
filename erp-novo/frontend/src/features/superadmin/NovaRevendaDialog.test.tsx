import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { vi, it, expect, afterEach } from 'vitest'
import { NovaRevendaDialog } from './SaEmpresasPage'
import { saApi, linkDoConvite } from './api'

afterEach(() => { vi.restoreAllMocks() })

function mount(onClose = vi.fn()) {
  vi.spyOn(saApi, 'get').mockResolvedValue({ data: { data: [
    { id: 1, nome: 'Essencial', slug: 'essencial', ativo: true, transitorio: false },
    { id: 9, nome: 'Legacy Full', slug: 'legacy_full', ativo: true, transitorio: true },
  ] } })
  render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><NovaRevendaDialog onClose={onClose} /></QueryClientProvider>)
  return onClose
}

async function preencher() {
  const user = userEvent.setup()
  await user.type(screen.getByLabelText(/Razão social/), 'Revenda Nova Ltda')
  await user.type(screen.getByLabelText(/Nome do dono/), 'Dona')
  await user.type(screen.getByLabelText(/E-mail do dono/), 'dona@nova.test')
  return user
}

it('cria a revenda e mostra o link de convite uma única vez, com aviso', async () => {
  mount()
  const post = vi.spyOn(saApi, 'post').mockResolvedValue({ data: { data: {
    empresa_id: 7, tenant_account_id: 3, plano: 'essencial', status_assinatura: 'trial', trial_ate: '2026-12-04',
    dono: { id: 5, nome: 'Dona', email: 'dona@nova.test' },
    convite: { token: 'tok-unico-123', expira_em: '2026-11-23T10:00:00Z' },
  } } })
  const user = await preencher()

  await user.click(screen.getByRole('button', { name: 'Criar revenda' }))

  expect(post).toHaveBeenCalledWith('/revendas', expect.objectContaining({
    razao_social: 'Revenda Nova Ltda', dono_email: 'dona@nova.test', plano_id: null, trial_dias: 14,
  }))
  const campo = await screen.findByLabelText('Link de convite do dono')
  expect((campo as HTMLInputElement).value).toBe(linkDoConvite({ email: 'dona@nova.test', token: 'tok-unico-123' }))
  expect((campo as HTMLInputElement).value).toContain('definir-senha?email=dona%40nova.test&token=tok-unico-123')
  expect(screen.getByRole('alert')).toHaveTextContent(/só uma vez/)
})

it('mostra o erro do backend no campo (e-mail já existente) e não fecha', async () => {
  const onClose = mount()
  vi.spyOn(saApi, 'post').mockRejectedValue({ response: { status: 422, data: {
    message: 'Já existe um usuário com este e-mail.', errors: { dono_email: ['Já existe um usuário com este e-mail.'] },
  } } })
  const user = await preencher()

  await user.click(screen.getByRole('button', { name: 'Criar revenda' }))

  expect(await screen.findByText('Já existe um usuário com este e-mail.')).toBeVisible()
  expect(screen.queryByLabelText('Link de convite do dono')).not.toBeInTheDocument()
  expect(onClose).not.toHaveBeenCalled()
})

it('não envia sem os campos obrigatórios', async () => {
  mount()
  const post = vi.spyOn(saApi, 'post')
  await waitFor(() => expect(screen.getByRole('button', { name: 'Criar revenda' })).toBeDisabled())
  expect(post).not.toHaveBeenCalled()
})
