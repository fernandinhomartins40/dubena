import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import axios from 'axios'
import { MemoryRouter } from 'react-router-dom'
import { vi, it, expect, afterEach } from 'vitest'
import { DefinirSenhaPage } from './DefinirSenhaPage'

afterEach(() => { vi.restoreAllMocks() })

function mount(url: string) {
  render(<MemoryRouter initialEntries={[url]}><DefinirSenhaPage /></MemoryRouter>)
}

it('troca o convite pela senha e manda para o login', async () => {
  vi.spyOn(axios, 'get').mockResolvedValue({ data: {} })
  const post = vi.spyOn(axios, 'post').mockResolvedValue({ data: { message: 'ok' } })
  mount('/definir-senha?email=dona%40nova.test&token=tok-1')
  const user = userEvent.setup()

  await user.type(screen.getByLabelText(/Nova senha/), 'Senha-Forte-2026')
  await user.type(screen.getByLabelText(/Repita a senha/), 'Senha-Forte-2026')
  await user.click(screen.getByRole('button', { name: 'Definir senha' }))

  expect(post).toHaveBeenCalledWith(expect.stringMatching(/\/api\/definir-senha$/),
    { email: 'dona@nova.test', token: 'tok-1', password: 'Senha-Forte-2026', password_confirmation: 'Senha-Forte-2026' }, expect.anything())
  expect(await screen.findByRole('status')).toHaveTextContent('Senha definida')
  expect(screen.getByRole('link', { name: 'Ir para o login' })).toHaveAttribute('href', '/login')
})

it('convite vencido mostra a mensagem do backend', async () => {
  vi.spyOn(axios, 'get').mockResolvedValue({ data: {} })
  vi.spyOn(axios, 'post').mockRejectedValue({ response: { status: 422, data: { message: 'Convite inválido ou vencido. Peça um novo ao suporte da plataforma.' } } })
  mount('/definir-senha?email=dona%40nova.test&token=velho')
  const user = userEvent.setup()

  await user.type(screen.getByLabelText(/Nova senha/), 'Senha-Forte-2026')
  await user.type(screen.getByLabelText(/Repita a senha/), 'Senha-Forte-2026')
  await user.click(screen.getByRole('button', { name: 'Definir senha' }))

  expect(await screen.findByRole('alert')).toHaveTextContent('Convite inválido ou vencido')
})

it('senhas diferentes não chegam ao servidor', async () => {
  const post = vi.spyOn(axios, 'post')
  mount('/definir-senha?email=a%40b.test&token=t')
  const user = userEvent.setup()

  await user.type(screen.getByLabelText(/Nova senha/), 'Senha-Forte-2026')
  await user.type(screen.getByLabelText(/Repita a senha/), 'Outra-Senha-2026')
  await user.click(screen.getByRole('button', { name: 'Definir senha' }))

  expect(screen.getByRole('alert')).toHaveTextContent('As senhas não conferem.')
  expect(post).not.toHaveBeenCalled()
})

it('link sem token não oferece formulário', () => {
  mount('/definir-senha?email=a%40b.test')
  expect(screen.getByRole('alert')).toHaveTextContent('Link de convite incompleto')
  expect(screen.queryByRole('button', { name: 'Definir senha' })).not.toBeInTheDocument()
})
