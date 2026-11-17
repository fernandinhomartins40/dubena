import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { AlertTriangle, ChevronDown, MapPin, Phone, Plus, Repeat, Trash2, UserPlus } from 'lucide-react'
import {
  Button, Field, AsyncSelect, Input, ConfirmDialog, FormDialog,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import { brl, data as fmtData } from '@/lib/format'
import { api } from '@/lib/api'
import { useCriarPedido, useSugestaoPedido, type Opcao } from './api'

interface Item {
  produto_id: number | null
  produtoLabel: string | null
  quantidade: string
  preco_unitario: string
  origem: string | null
  /** Preço digitado à mão não é sobrescrito quando a condição muda. */
  manual: boolean
}

const itemVazio = (): Item => ({ produto_id: null, produtoLabel: null, quantidade: '1', preco_unitario: '', origem: null, manual: false })

/**
 * "Novo pedido" em três blocos: para quem → o que vai → como paga.
 *
 * Antes eram onze campos obrigatórios antes do primeiro item, seis deles sem
 * coluna no banco (colaborador e endereço: a entrega é no endereço do cliente)
 * e o setor com o nome errado — o atendente digitava e o servidor descartava.
 * Agora, ao escolher o cliente, o sistema mostra o endereço de entrega e
 * sugere condição, operação, setor e itens pelo último pedido dele, com o
 * preço de hoje. Tudo continua editável; quem decide preço final, alçada e
 * estoque é o servidor.
 *
 * Abre também por `?novo=1` (o botão "Novo" do topo).
 */
export function NovoPedidoDialog() {
  const { can } = useAuth()
  const criar = useCriarPedido()
  const [params, setParams] = useSearchParams()
  const [open, setOpen] = useState(false)
  const [clienteId, setClienteId] = useState<number | null>(null)
  const [clienteLabel, setClienteLabel] = useState<string | null>(null)
  const [situacao, setSituacao] = useState<Opcao | null>(null)
  const [condicao, setCondicao] = useState<Opcao | null>(null)
  const [operacao, setOperacao] = useState<Opcao | null>(null)
  const [setor, setSetor] = useState<Opcao | null>(null)
  const [itens, setItens] = useState<Item[]>([])
  const [maisOpcoes, setMaisOpcoes] = useState(false)
  const [tentou, setTentou] = useState(false)
  const [confirmarDescarte, setConfirmarDescarte] = useState(false)
  const [novoCliente, setNovoCliente] = useState(false)
  const sugestao = useSugestaoPedido(open ? clienteId : null)

  // Abertura pela URL: o atalho global leva para cá sem saber o que há na página.
  useEffect(() => {
    if (params.get('novo') === '1') setOpen(true)
  }, [params])

  // Ao chegar a sugestão do cliente, preenche só o que ainda está vazio: se o
  // atendente já escolheu algo, a escolha dele vale.
  const s = sugestao.data
  useEffect(() => {
    if (!s) return
    setSituacao((v) => v ?? s.situacao_inicial)
    setCondicao((v) => v ?? s.ultimo_pedido?.condicaopagamento ?? null)
    setOperacao((v) => v ?? s.ultimo_pedido?.pedidooperacao ?? null)
    setSetor((v) => v ?? s.ultimo_pedido?.setor ?? null)
  }, [s])

  if (!can('pedido.create')) return null

  // R2-10: Esc, clicar fora ou "Cancelar" descartavam cliente e itens sem
  // perguntar — o atendente perdia o pedido no meio da ligação. Com algo
  // preenchido, fechar passa por uma confirmação.
  const preenchido = clienteId !== null || itens.length > 0
  function pedirParaFechar(v: boolean) {
    if (!v && preenchido && !criar.isPending) { setConfirmarDescarte(true); return }
    fechar(v)
  }

  function fechar(v: boolean) {
    setOpen(v)
    if (!v) {
      setConfirmarDescarte(false)
      setClienteId(null); setClienteLabel(null); setSituacao(null); setCondicao(null); setOperacao(null); setSetor(null)
      setItens([]); setMaisOpcoes(false); setTentou(false)
      if (params.has('novo')) { params.delete('novo'); setParams(params, { replace: true }) }
    }
  }

  async function precos(produtos: number[], condicaoId: number | null, cliente = clienteId): Promise<Record<string, { valor: number; origem: string }>> {
    if (!cliente || produtos.length === 0) return {}
    try {
      return (await api.get('/pedidos/precos', { params: { cliente_id: cliente, condicaopagamento_id: condicaoId ?? undefined, produtos } })).data.data ?? {}
    } catch {
      return {} // sem sugestão o atendente digita, como antes — não bloqueia a venda
    }
  }

  function repetirUltimo() {
    const ult = s?.ultimo_pedido
    if (!ult) return
    setItens(ult.itens.map((i) => ({
      produto_id: i.produto_id, produtoLabel: i.produto, quantidade: String(i.quantidade),
      preco_unitario: i.preco_unitario != null ? String(i.preco_unitario) : '', origem: i.origem_preco, manual: false,
    })))
  }

  async function escolherProduto(idx: number, id: number | null, label: string | null) {
    setItem(idx, { produto_id: id, produtoLabel: label, manual: false })
    if (!id) return
    const p = (await precos([id], condicao?.id ?? null))[id]
    if (p) setItens((atual) => atual.map((it, k) => (k === idx && !it.manual ? { ...it, preco_unitario: String(p.valor), origem: p.origem } : it)))
  }

  async function trocarCondicao(nova: Opcao | null) {
    setCondicao(nova)
    const ids = itens.filter((i) => i.produto_id && !i.manual).map((i) => i.produto_id as number)
    const tabela = await precos(ids, nova?.id ?? null)
    setItens((atual) => atual.map((it) => (it.produto_id && !it.manual && tabela[it.produto_id] ? { ...it, preco_unitario: String(tabela[it.produto_id].valor), origem: tabela[it.produto_id].origem } : it)))
  }

  function setItem(i: number, patch: Partial<Item>) { setItens((atual) => atual.map((it, k) => (k === i ? { ...it, ...patch } : it))) }

  // R2-09: item sem produto ou com quantidade 0/inválida era DESCARTADO em
  // silêncio — o pedido saía com menos itens do que o atendente montou e o
  // toast dizia "criado". Agora cada linha incompleta é apontada e bloqueia.
  const problemaDoItem = (i: Item): string | null => {
    if (!i.produto_id) return 'escolha o produto ou remova a linha'
    const q = quantidade(i.quantidade)
    if (q === null) return 'quantidade inválida'
    if (q <= 0) return 'a quantidade precisa ser maior que zero'
    return null
  }
  const problemas = itens.map(problemaDoItem)
  const validos = itens.filter((_, k) => problemas[k] === null)
  const total = validos.reduce((soma, i) => soma + (Number(i.preco_unitario) || 0) * (quantidade(i.quantidade) ?? 0), 0)
  const faltando = [
    !clienteId && 'o cliente',
    itens.length === 0 && 'ao menos um item',
    ...problemas.flatMap((p, k) => (p ? [`no item ${k + 1}, ${p}`] : [])),
    !condicao && 'a condição de pagamento',
    !situacao && 'a situação',
    !operacao && 'a operação',
    !setor && 'o setor de entrega',
  ].filter(Boolean) as string[]

  async function salvar() {
    setTentou(true)
    if (faltando.length > 0) {
      // As três últimas moram em "mais opções": abre para a pessoa ver o que falta.
      if (!situacao || !operacao || !setor) setMaisOpcoes(true)
      toast.error(`Falta informar ${faltando.join(', ')}.`)
      return
    }
    try {
      const salvo = await criar.mutateAsync({
        cliente_id: clienteId,
        pedidosituacao_id: situacao!.id,
        condicaopagamento_id: condicao!.id,
        pedidooperacao_id: operacao!.id,
        setor_id: setor!.id,
        itens: validos.map((i) => ({
          produto_id: i.produto_id,
          quantidade: quantidade(i.quantidade),
          // Vazio = o servidor usa o preço de venda; não manda zero por engano.
          ...(i.preco_unitario !== '' ? { preco_unitario: Number(i.preco_unitario) } : {}),
        })),
      })
      toast.success(`Pedido #${salvo.id} criado.`)
      fechar(false)
    } catch (e: any) {
      // P2-D-12: o servidor recusa fração de produto por unidade e quantidade
      // acima do teto da revenda; a mensagem dele diz qual item e por quê.
      const erros = e?.response?.data?.errors as Record<string, string[]> | undefined
      const primeiro = erros ? Object.values(erros)[0]?.[0] : undefined
      toast.error(primeiro ?? e?.response?.data?.message ?? 'Não foi possível criar o pedido.')
    }
  }

  async function clienteCadastrado(c: { id: number; nome: string; reconhecido: boolean }) {
    setNovoCliente(false)
    setClienteId(c.id); setClienteLabel(c.nome)
    setCondicao(null); setOperacao(null); setSetor(null); setItens([])
    toast.success(c.reconhecido ? `${c.nome} já estava cadastrado e foi escolhido.` : `Cliente ${c.nome} cadastrado.`)
  }

  const endereco = s?.cliente.endereco
  const ult = s?.ultimo_pedido

  return (
    <>
    <Dialog open={open} onOpenChange={pedirParaFechar}>
      <DialogTrigger asChild><Button><Plus size={16} /> Novo pedido</Button></DialogTrigger>
      <DialogContent className="max-w-3xl">
        <DialogHeader><DialogTitle>Novo pedido</DialogTitle></DialogHeader>

        <section aria-labelledby="np-quem" className="space-y-3">
          <h3 id="np-quem" className="text-sm font-semibold"><Passo n={1} /> Para quem?</h3>
          <Field label="Cliente" required error={tentou && !clienteId ? 'Escolha o cliente.' : undefined}>
            <AsyncSelect endpoint="/lookups/clientes-fornecedores" value={clienteId} valueLabel={clienteLabel} placeholder="Busque pelo nome ou telefone…"
              onChange={(id, o) => {
                setClienteId(id); setClienteLabel(o?.label ?? null)
                // Cliente novo = sugestão nova: o que veio do cliente anterior não vale.
                setCondicao(null); setOperacao(null); setSetor(null); setItens([])
              }} />
          </Field>
          {can('cliente.create') && (
            <Button type="button" variant="link" size="sm" className="h-auto px-0" onClick={() => setNovoCliente(true)}>
              <UserPlus size={15} /> Cadastrar cliente novo sem sair do pedido
            </Button>
          )}
          {clienteId && sugestao.isLoading && <p className="text-sm text-muted-foreground">Buscando endereço e último pedido…</p>}
          {s && (
            <div className="rounded-lg border bg-secondary/40 p-3 text-sm">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="space-y-1">
                  {endereco?.texto
                    ? <p className="flex items-start gap-1.5"><MapPin size={15} className="mt-0.5 shrink-0 text-muted-foreground" /><span><span className="text-muted-foreground">Entregar em </span>{endereco.texto}{endereco.referencia ? <span className="text-muted-foreground"> · {endereco.referencia}</span> : null}</span></p>
                    : null}
                  {s.cliente.telefone && <p className="flex items-center gap-1.5"><Phone size={14} className="text-muted-foreground" />{s.cliente.telefone}</p>}
                  {!endereco?.completo && (
                    <p className="flex items-center gap-1.5 font-medium text-destructive"><AlertTriangle size={15} />
                      Endereço incompleto — o entregador pode não achar.
                      <Link to={`/clientes/${s.cliente.id}`} target="_blank" className="underline underline-offset-2">Completar o cadastro</Link>
                    </p>
                  )}
                </div>
                {ult && ult.itens.length > 0 && (
                  <Button type="button" variant="secondary" size="sm" onClick={repetirUltimo}><Repeat size={15} /> Repetir último pedido</Button>
                )}
              </div>
              {ult
                ? <p className="mt-2 text-muted-foreground">Último pedido em {fmtData(ult.datahora)}: {ult.itens.map((i) => `${i.quantidade}× ${i.produto}`).join(', ') || '—'}</p>
                : <p className="mt-2 text-muted-foreground">Primeiro pedido deste cliente.</p>}
            </div>
          )}
        </section>

        <section aria-labelledby="np-oque" className="mt-2 space-y-2">
          <div className="flex items-center justify-between">
            <h3 id="np-oque" className="text-sm font-semibold"><Passo n={2} /> O que vai?</h3>
            <Button variant="outline" size="sm" onClick={() => setItens([...itens, itemVazio()])}><Plus size={16} /> Adicionar item</Button>
          </div>
          {itens.length === 0 && (
            <p className={`rounded-lg border border-dashed p-3 text-sm ${tentou ? 'border-destructive text-destructive' : 'text-muted-foreground'}`}>
              {ult?.itens.length ? 'Use "Repetir último pedido" ou adicione os itens.' : 'Adicione ao menos um item.'}
            </p>
          )}
          {itens.map((it, i) => (
            <div key={i} className="grid grid-cols-12 items-end gap-2">
              <div className="col-span-12 sm:col-span-6"><Field label="Produto" error={tentou && !it.produto_id ? 'Escolha o produto ou remova a linha.' : undefined}><AsyncSelect endpoint="/lookups/produtos" value={it.produto_id} valueLabel={it.produtoLabel} onChange={(id, o) => { void escolherProduto(i, id, o?.label ?? null) }} /></Field></div>
              <div className="col-span-4 sm:col-span-2"><Field label="Qtd" error={tentou && problemas[i] && it.produto_id ? problemas[i]! : undefined}><Input inputMode="decimal" value={it.quantidade} onChange={(e) => setItem(i, { quantidade: e.target.value })} /></Field></div>
              <div className="col-span-6 sm:col-span-3">
                <Field label="Preço unit." hint={it.origem ?? undefined}>
                  <Input type="number" step="0.01" inputMode="decimal" value={it.preco_unitario} placeholder="Preço de venda"
                    onChange={(e) => setItem(i, { preco_unitario: e.target.value, manual: true, origem: 'Digitado' })} />
                </Field>
              </div>
              <div className="col-span-2 flex justify-end sm:col-span-1"><Button variant="ghost" size="icon" aria-label={`Remover item ${i + 1}`} onClick={() => setItens(itens.filter((_, k) => k !== i))}><Trash2 size={16} /></Button></div>
            </div>
          ))}
        </section>

        <section aria-labelledby="np-como" className="mt-2 space-y-3">
          <h3 id="np-como" className="text-sm font-semibold"><Passo n={3} /> Como paga?</h3>
          <Field label="Condição de pagamento" required error={tentou && !condicao ? 'Escolha a condição.' : undefined}>
            <AsyncSelect endpoint="/lookups/condicoes-pagamento" value={condicao?.id ?? null} valueLabel={condicao?.label} onChange={(id, o) => { void trocarCondicao(id ? { id, label: o?.label ?? '' } : null) }}
              // P2-D-13: revenda sem condição cadastrada via "Nenhum resultado." e o
              // salvar pedia a condição — sem dizer que ela precisa ser criada antes.
              vazio={<>Nenhuma condição encontrada. Se a revenda ainda não tem condições de pagamento, {can('financeiro.edit')
                ? <Link to="/financeiro" target="_blank" className="font-medium text-foreground underline underline-offset-2">cadastre em Financeiro</Link>
                : 'peça ao administrador para cadastrá-las em Financeiro'}.</>} />
          </Field>
          <button type="button" className="flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground" aria-expanded={maisOpcoes} onClick={() => setMaisOpcoes((v) => !v)}>
            <ChevronDown size={15} className={maisOpcoes ? '' : '-rotate-90'} />
            Mais opções{!maisOpcoes && situacao && operacao && setor ? ` — ${situacao.label} · ${operacao.label} · ${setor.label}` : ''}
          </button>
          {maisOpcoes && (
            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
              <Field label="Situação" required error={tentou && !situacao ? 'Obrigatória.' : undefined}><AsyncSelect endpoint="/lookups/pedido-situacoes" value={situacao?.id ?? null} valueLabel={situacao?.label} onChange={(id, o) => setSituacao(id ? { id, label: o?.label ?? '' } : null)} /></Field>
              <Field label="Operação" required error={tentou && !operacao ? 'Obrigatória.' : undefined}><AsyncSelect endpoint="/lookups/pedido-operacoes" value={operacao?.id ?? null} valueLabel={operacao?.label} onChange={(id, o) => setOperacao(id ? { id, label: o?.label ?? '' } : null)} /></Field>
              <Field label="Setor de entrega" required error={tentou && !setor ? 'Obrigatório.' : undefined}><AsyncSelect endpoint="/lookups/setores" value={setor?.id ?? null} valueLabel={setor?.label} onChange={(id, o) => setSetor(id ? { id, label: o?.label ?? '' } : null)} /></Field>
            </div>
          )}
        </section>

        <DialogFooter className="items-center gap-2 sm:justify-between">
          <p className="text-sm">Total <span className="text-lg font-bold tabular-nums">{brl(total)}</span></p>
          <div className="flex gap-2">
            <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
            <Button loading={criar.isPending} onClick={() => { void salvar() }}>Criar pedido</Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
    <ConfirmDialog open={confirmarDescarte} onOpenChange={setConfirmarDescarte} title="Descartar este pedido?"
      description="O cliente e os itens escolhidos ainda não foram salvos. Fechar agora descarta o pedido."
      confirmLabel="Descartar pedido" onConfirm={() => fechar(false)} />
    {novoCliente && <NovoClienteRapido onClose={() => setNovoCliente(false)} onSalvo={(c) => { void clienteCadastrado(c) }} />}
    </>
  )
}

/**
 * Quantidade como o atendente digita: "2", "1,5" ou "1.5". Notação científica
 * ("1e3" virava 1.000 botijões, P2-D-12), sinal e texto são inválidos.
 * Se a fração vale para o produto, quem decide é o servidor (unidade "UN").
 */
export function quantidade(texto: string): number | null {
  const t = texto.trim()
  if (!/^\d+([.,]\d+)?$/.test(t)) return null
  return Number(t.replace(',', '.'))
}

/**
 * P2-A-13: cadastrar o cliente que está ao telefone SEM sair do Novo pedido.
 * Nome e telefone bastam (o servidor exige telefone ou endereço); se o
 * telefone/nome já identificam um cadastro, o servidor devolve ESSE cliente
 * em vez de duplicar — e a tela avisa.
 */
function NovoClienteRapido({ onClose, onSalvo }: { onClose: () => void; onSalvo: (c: { id: number; nome: string; reconhecido: boolean }) => void }) {
  const [nome, setNome] = useState('')
  const [telefone, setTelefone] = useState('')
  const [erros, setErros] = useState<Record<string, string>>({})
  const [salvando, setSalvando] = useState(false)

  async function salvar() {
    const local: Record<string, string> = {}
    if (!nome.trim()) local.nome = 'Informe o nome do cliente.'
    if (telefone.replace(/\D/g, '').length < 8) local.telefone = 'Informe o telefone com DDD.'
    setErros(local)
    if (Object.keys(local).length) return
    setSalvando(true)
    try {
      const r = await api.post('/clientes', { nome: nome.trim(), telefones: [{ telefone: telefone.trim() }] })
      onSalvo({ id: r.data.data.id, nome: r.data.data.nome, reconhecido: r.status === 200 })
    } catch (e: any) {
      const srv = (e?.response?.data?.errors ?? {}) as Record<string, string[]>
      setErros({
        nome: srv.nome?.[0] ?? '',
        telefone: srv['telefones.0.telefone']?.[0] ?? '',
        geral: Object.keys(srv).length ? '' : (e?.response?.data?.message ?? 'Não foi possível cadastrar o cliente.'),
      })
    } finally { setSalvando(false) }
  }

  return (
    <FormDialog open onOpenChange={(v) => { if (!v) onClose() }} title="Cliente novo"
      description="Nome e telefone de quem está ligando. O endereço pode ser completado depois no cadastro."
      confirmLabel="Cadastrar e usar no pedido" loading={salvando} onConfirm={() => { void salvar() }}
      dirty={nome !== '' || telefone !== ''}>
      <Field label="Nome" required error={erros.nome || undefined}>
        <Input value={nome} autoFocus onChange={(e) => setNome(e.target.value)} />
      </Field>
      <Field label="Telefone" required error={erros.telefone || undefined}>
        <Input type="tel" inputMode="tel" value={telefone} placeholder="(42) 99999-0000" onChange={(e) => setTelefone(e.target.value)} />
      </Field>
      {erros.geral && <p role="alert" className="text-sm text-destructive">{erros.geral}</p>}
    </FormDialog>
  )
}

function Passo({ n }: { n: number }) {
  return <span aria-hidden className="mr-1.5 inline-grid size-5 place-items-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground">{n}</span>
}
