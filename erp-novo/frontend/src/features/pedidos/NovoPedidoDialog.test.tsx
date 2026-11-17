import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { NovoPedidoDialog, quantidade } from './NovoPedidoDialog'

vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: () => true }) }))

const sugestao = {
  cliente: { id: 7, nome: 'Joana', telefone: '42999990000', endereco: { texto: 'Rua das Flores, 45 · Centro — Guarapuava/PR', referencia: 'Portão azul', completo: true } },
  situacao_inicial: { id: 1, label: 'Novo' },
  ultimo_pedido: {
    id: 99, datahora: '2026-09-20T10:00:00-03:00',
    condicaopagamento: { id: 3, label: 'Pix' }, pedidooperacao: { id: 4, label: 'Disk' }, setor: { id: 5, label: 'Depósito' },
    itens: [{ produto_id: 10, produto: 'Botijão P13', quantidade: 2, preco_unitario: 105, origem_preco: 'Preço combinado com o cliente' }],
  },
}

function montar(ultimo = true) {
  vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
    if (url === '/lookups/clientes-fornecedores') return { data: { data: [{ id: 7, label: 'Joana' }] } } as never
    if (url === '/pedidos/sugestao') return { data: { data: ultimo ? sugestao : { ...sugestao, ultimo_pedido: null } } } as never
    return { data: { data: [] } } as never
  })
  const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: { id: 501 } } })
  render(
    <MemoryRouter initialEntries={['/pedidos?novo=1']}>
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <NovoPedidoDialog />
      </QueryClientProvider>
    </MemoryRouter>,
  )
  return post
}

async function escolherCliente(user: ReturnType<typeof userEvent.setup>) {
  const dialogo = await screen.findByRole('dialog')
  await user.click(within(dialogo).getByRole('button', { name: /^Cliente/ }))
  await user.click(await screen.findByRole('button', { name: 'Joana' }))
  return dialogo
}

afterEach(() => vi.restoreAllMocks())

it('abre pela URL e, ao escolher o cliente, mostra o endereço e repete o último pedido em três passos', async () => {
  const post = montar()
  const user = userEvent.setup()
  const dialogo = await escolherCliente(user)

  expect(await within(dialogo).findByText(/Rua das Flores, 45/)).toBeInTheDocument()
  expect(within(dialogo).getByText(/Último pedido em .*2× Botijão P13/)).toBeInTheDocument()

  // 2º passo: um clique traz os itens com o preço de hoje e a origem dele.
  await user.click(within(dialogo).getByRole('button', { name: /Repetir último pedido/ }))
  expect(within(dialogo).getByDisplayValue('105')).toBeInTheDocument()
  expect(within(dialogo).getByText('Preço combinado com o cliente')).toBeInTheDocument()

  // 3º passo: condição, situação, operação e setor já vieram — só concluir.
  await user.click(within(dialogo).getByRole('button', { name: 'Criar pedido' }))
  await waitFor(() => expect(post).toHaveBeenCalledWith('/pedidos', {
    cliente_id: 7, pedidosituacao_id: 1, condicaopagamento_id: 3, pedidooperacao_id: 4, setor_id: 5,
    itens: [{ produto_id: 10, quantidade: 2, preco_unitario: 105 }],
  }))
})

it('não envia os campos que o servidor descartava (colaborador e endereço)', async () => {
  const post = montar()
  const user = userEvent.setup()
  const dialogo = await escolherCliente(user)
  await user.click(await within(dialogo).findByRole('button', { name: /Repetir último pedido/ }))
  await user.click(within(dialogo).getByRole('button', { name: 'Criar pedido' }))
  await waitFor(() => expect(post).toHaveBeenCalled())
  const enviado = post.mock.calls[0][1] as Record<string, unknown>
  for (const campo of ['colaborador_id', 'entregasetor_id', 'entregacidade_id', 'entregarua_id', 'entreganumero', 'ufentrega']) {
    expect(enviado).not.toHaveProperty(campo)
  }
})

it('sem itens, diz o que falta e não envia', async () => {
  const post = montar(false)
  const user = userEvent.setup()
  const dialogo = await escolherCliente(user)
  expect(await within(dialogo).findByText('Primeiro pedido deste cliente.')).toBeInTheDocument()
  await user.click(within(dialogo).getByRole('button', { name: 'Criar pedido' }))
  expect(post).not.toHaveBeenCalled()
  expect(within(dialogo).getByText('Adicione ao menos um item.')).toBeInTheDocument()
})

// ---- Rodada 3 (Agente A) ----------------------------------------------------

it('R2-09: item com quantidade 0 bloqueia e diz qual item, em vez de sumir do pedido', async () => {
  const post = montar()
  const user = userEvent.setup()
  const dialogo = await escolherCliente(user)
  await user.click(await within(dialogo).findByRole('button', { name: /Repetir último pedido/ }))
  const qtd = within(dialogo).getByRole('textbox', { name: /Qtd/ })
  await user.clear(qtd); await user.type(qtd, '0')
  await user.click(within(dialogo).getByRole('button', { name: 'Criar pedido' }))
  expect(post).not.toHaveBeenCalled()
  expect(within(dialogo).getByText('a quantidade precisa ser maior que zero')).toBeInTheDocument()
})

it('P2-D-12: "1e3" não vira mil botijões; "1,5" vai como 1.5 e quem decide a fração é o servidor', async () => {
  expect(quantidade('1e3')).toBeNull()
  expect(quantidade('-2')).toBeNull()
  expect(quantidade('abc')).toBeNull()
  expect(quantidade('1,5')).toBe(1.5)
  expect(quantidade(' 2 ')).toBe(2)
})

it('R2-10: Esc com o pedido montado pergunta antes de descartar', async () => {
  montar()
  const user = userEvent.setup()
  const dialogo = await escolherCliente(user)
  await within(dialogo).findByText(/Rua das Flores, 45/)
  await user.keyboard('{Escape}')
  expect(await screen.findByRole('dialog', { name: 'Descartar este pedido?' })).toBeInTheDocument()
  // "Cancelar" na confirmação devolve ao pedido, com o cliente preservado.
  await user.click(within(screen.getByRole('dialog', { name: 'Descartar este pedido?' })).getByRole('button', { name: 'Cancelar' }))
  expect(screen.getByRole('dialog', { name: 'Novo pedido' })).toHaveTextContent('Joana')
})

it('P2-D-15: Enter na busca escolhe o primeiro resultado, sem mouse', async () => {
  montar()
  const user = userEvent.setup()
  const dialogo = await screen.findByRole('dialog')
  await user.click(within(dialogo).getByRole('button', { name: /^Cliente/ }))
  await screen.findByRole('button', { name: 'Joana' })
  await user.keyboard('{Enter}')
  expect(await within(dialogo).findByText(/Rua das Flores, 45/)).toBeInTheDocument()
})

it('P2-A-13: cadastra o cliente novo sem sair do pedido e já o escolhe', async () => {
  const post = montar()
  post.mockResolvedValueOnce({ status: 201, data: { data: { id: 88, nome: 'Carlos Novo' } } } as never)
  const user = userEvent.setup()
  const dialogo = await screen.findByRole('dialog')
  await user.click(within(dialogo).getByRole('button', { name: /Cadastrar cliente novo/ }))
  const form = await screen.findByRole('dialog', { name: 'Cliente novo' })
  await user.type(within(form).getByRole('textbox', { name: /Nome/ }), 'Carlos Novo')
  await user.type(within(form).getByRole('textbox', { name: /Telefone/ }), '(42) 99123-4567')
  await user.click(within(form).getByRole('button', { name: 'Cadastrar e usar no pedido' }))
  await waitFor(() => expect(post).toHaveBeenCalledWith('/clientes', { nome: 'Carlos Novo', telefones: [{ telefone: '(42) 99123-4567' }] }))
  expect(await screen.findByRole('dialog', { name: 'Novo pedido' })).toHaveTextContent('Carlos Novo')
})
