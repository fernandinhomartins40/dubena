import { useState } from 'react'
import { Plus, Truck, TrendingDown, Info } from 'lucide-react'
import {
  Button, Badge, DataTable, type Column, EmptyState, AsyncState, Field, Input,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  FormDialog, RowActions, AsyncSelect, Switch, toast,
} from '@/components/ui'
import { brl } from '@/lib/format'
import {
  useTaxasEntrega, useSalvarTaxa, useExcluirTaxa,
  type RegraTaxa, type CriterioTaxa,
} from './api'

/** Rótulos e ajuda de cada critério — a ordem reflete a precedência real. */
const CRITERIOS: { valor: CriterioTaxa; rotulo: string; ajuda: string }[] = [
  { valor: 'valor_pedido', rotulo: 'Valor do pedido', ajuda: 'Ex.: frete grátis acima de R$ 150. Vence as demais regras.' },
  { valor: 'bairro', rotulo: 'Bairro', ajuda: 'Preço fixo para um bairro.' },
  { valor: 'distancia', rotulo: 'Distância (km)', ajuda: 'Faixa de km. Só vale para cliente com endereço geolocalizado.' },
  { valor: 'cidade', rotulo: 'Cidade', ajuda: 'Preço para toda uma cidade.' },
  { valor: 'padrao', rotulo: 'Padrão da empresa', ajuda: 'Vale quando nenhuma outra regra casa.' },
]

const VAZIO = {
  descricao: '', criterio: 'padrao' as CriterioTaxa,
  bairro_id: null as number | null, cidade_id: null as number | null,
  faixa_de: null as number | null, faixa_ate: null as number | null,
  valor: 0, isenta: false, custo_estimado: null as number | null,
  prioridade: 0, ativo: true,
}

/**
 * Tabela de taxas de entrega.
 *
 * Antes disto o valor era digitado à mão em cada venda — sem tabela por bairro,
 * sem isenção por valor mínimo e sem custo. Não havia como responder "a entrega
 * neste bairro dá lucro?".
 */
export function TaxasEntregaTab() {
  const { data, isLoading, error } = useTaxasEntrega()
  const salvar = useSalvarTaxa()
  const excluir = useExcluirTaxa()

  const [editando, setEditando] = useState<RegraTaxa | null>(null)
  const [criando, setCriando] = useState(false)
  const [form, setForm] = useState({ ...VAZIO })


  function abrirNovo() {
    setForm({ ...VAZIO })
    setCriando(true)
  }

  function abrirEdicao(r: RegraTaxa) {
    setForm({
      descricao: r.descricao, criterio: r.criterio,
      bairro_id: r.bairro_id, cidade_id: r.cidade_id,
      faixa_de: r.faixa_de, faixa_ate: r.faixa_ate,
      valor: r.valor, isenta: r.isenta, custo_estimado: r.custo_estimado,
      prioridade: r.prioridade, ativo: r.ativo,
    })
    setEditando(r)
  }

  function campo<K extends keyof typeof VAZIO>(k: K, v: (typeof VAZIO)[K]) {
    setForm((f) => ({ ...f, [k]: v }))
  }

  async function remover(r: RegraTaxa) {
    try {
      await excluir.mutateAsync(r.id)
      toast.success('Regra removida.')
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Nao foi possivel remover.')
    }
  }

  async function confirmar() {
    try {
      await salvar.mutateAsync({ id: editando?.id ?? null, data: form })
      toast.success(editando ? 'Regra atualizada.' : 'Regra criada.')
      setEditando(null)
      setCriando(false)
    } catch (e: any) {
      const erros = e?.response?.data?.errors
      toast.error(erros ? Object.values(erros).flat()[0] as string : 'Não foi possível salvar.')
    }
  }

  const columns: Column<RegraTaxa>[] = [
    {
      key: 'descricao', header: 'Regra',
      cell: (r) => (
        <div className="min-w-0">
          <div className="font-medium truncate flex items-center gap-2">
            {r.descricao}
            {!r.ativo && <Badge variant="secondary">inativa</Badge>}
            {r.isenta && <Badge>grátis</Badge>}
          </div>
          <div className="text-xs text-muted-foreground">
            {CRITERIOS.find((c) => c.valor === r.criterio)?.rotulo ?? r.criterio}
            {r.bairro && ` · ${r.bairro}`}
            {r.cidade && ` · ${r.cidade}`}
            {r.faixa_de !== null && ` · de ${r.faixa_de}`}
            {r.faixa_ate !== null && ` até ${r.faixa_ate}`}
          </div>
        </div>
      ),
    },
    { key: 'valor', header: 'Cobra', align: 'right', cell: (r) => <span className="tabular-nums">{r.isenta ? '—' : brl(r.valor)}</span> },
    { key: 'custo', header: 'Custa', align: 'right', cell: (r) => <span className="tabular-nums text-muted-foreground">{r.custo_estimado !== null ? brl(r.custo_estimado) : '—'}</span> },
    {
      key: 'margem', header: 'Margem', align: 'right',
      cell: (r) => {
        if (r.margem === null) return <span className="text-muted-foreground">—</span>
        // Margem negativa é o achado que a tabela existe para revelar.
        const negativa = r.margem < 0
        return (
          <span className={negativa ? 'tabular-nums font-medium text-destructive' : 'tabular-nums'}>
            {negativa && <TrendingDown size={13} className="mr-1 inline" />}
            {brl(r.margem)}
          </span>
        )
      },
    },
    {
      key: 'acoes', header: '', align: 'right', width: 'w-16',
      cell: (r) => (
        <RowActions
          onEdit={() => abrirEdicao(r)}
          onDelete={() => remover(r)}
          confirmMsg={`Remover a regra "${r.descricao}"? Pedidos ja feitos nao mudam.`}
        />
      ),
    },
  ]

  const criterio = CRITERIOS.find((c) => c.valor === form.criterio)
  const usaFaixa = form.criterio === 'distancia' || form.criterio === 'valor_pedido'

  return (
    <div className="space-y-4">
      <div className="flex items-start justify-between gap-4">
        <p className="max-w-2xl text-sm text-muted-foreground">
          As regras são avaliadas em ordem: <strong>valor do pedido</strong> (isenção)
          {' '}→ bairro → distância → cidade → padrão. A primeira que casar decide.
          Sem nenhuma regra, a entrega é gratuita.
        </p>
        <Button onClick={abrirNovo}><Plus size={16} /> Nova regra</Button>
      </div>

      <AsyncState loading={isLoading} error={error} skeletonRows={3}>
        <DataTable
          columns={columns}
          rows={data}
          rowKey={(r) => r.id}
          empty={
            <EmptyState
              icon={<Truck />}
              title="Nenhuma regra de entrega"
              description="Sem regras, toda entrega sai sem taxa. Crie uma regra para cobrar por bairro, distância ou um valor padrão."
              action={<Button onClick={abrirNovo}><Plus size={16} /> Nova regra</Button>}
            />
          }
        />
      </AsyncState>

      <FormDialog
        open={criando || editando !== null}
        onOpenChange={(o) => { if (!o) { setCriando(false); setEditando(null) } }}
        title={editando ? 'Editar regra' : 'Nova regra de entrega'}
        loading={salvar.isPending}
        onConfirm={confirmar}
      >
        <div className="space-y-4">
          <Field label="Descrição" required hint="Como esta regra aparece na lista.">
            <Input value={form.descricao} onChange={(e) => campo('descricao', e.target.value)}
              placeholder="Ex.: Bairro Centro, Frete grátis acima de R$ 150" />
          </Field>

          <Field label="Cobrar por" hint={criterio?.ajuda}>
            <Select value={form.criterio} onValueChange={(v) => campo('criterio', v as CriterioTaxa)}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                {CRITERIOS.map((c) => <SelectItem key={c.valor} value={c.valor}>{c.rotulo}</SelectItem>)}
              </SelectContent>
            </Select>
          </Field>

          {form.criterio === 'bairro' && (
            <Field label="Bairro" required>
              <AsyncSelect endpoint="/lookups/bairros" value={form.bairro_id}
                onChange={(id) => campo('bairro_id', id)} />
            </Field>
          )}

          {form.criterio === 'cidade' && (
            <Field label="Cidade" required>
              <AsyncSelect endpoint="/lookups/cidades" value={form.cidade_id}
                onChange={(id) => campo('cidade_id', id)} />
            </Field>
          )}

          {usaFaixa && (
            <div className="grid grid-cols-2 gap-3">
              <Field label={form.criterio === 'distancia' ? 'De (km)' : 'De (R$)'}>
                <Input type="number" step="0.01" value={form.faixa_de ?? ''}
                  onChange={(e) => campo('faixa_de', e.target.value === '' ? null : Number(e.target.value))} />
              </Field>
              <Field label={form.criterio === 'distancia' ? 'Até (km)' : 'Até (R$)'}
                hint="Em branco = sem limite.">
                <Input type="number" step="0.01" value={form.faixa_ate ?? ''}
                  onChange={(e) => campo('faixa_ate', e.target.value === '' ? null : Number(e.target.value))} />
              </Field>
            </div>
          )}

          <div className="flex items-center justify-between rounded-md border p-3">
            <div>
              <div className="text-sm font-medium">Entrega grátis</div>
              <div className="text-xs text-muted-foreground">Zera a cobrança mesmo com valor preenchido.</div>
            </div>
            <Switch checked={form.isenta} onCheckedChange={(v) => campo('isenta', v)} />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <Field label="Cobrar do cliente (R$)" required>
              <Input type="number" step="0.01" value={form.valor} disabled={form.isenta}
                onChange={(e) => campo('valor', Number(e.target.value))} />
            </Field>
            <Field label="Custo estimado (R$)"
              hint="Combustível, tempo do entregador. É o que permite ver a margem.">
              <Input type="number" step="0.01" value={form.custo_estimado ?? ''}
                onChange={(e) => campo('custo_estimado', e.target.value === '' ? null : Number(e.target.value))} />
            </Field>
          </div>

          <Field label="Prioridade"
            hint="Desempata entre regras do MESMO tipo. Maior vence.">
            <Input type="number" value={form.prioridade}
              onChange={(e) => campo('prioridade', Number(e.target.value))} />
          </Field>

          {form.custo_estimado !== null && !form.isenta && form.valor - form.custo_estimado < 0 && (
            <div className="flex items-start gap-2 rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm">
              <Info size={16} className="mt-0.5 shrink-0 text-destructive" />
              <span>
                Esta regra dá <strong>prejuízo</strong> de {brl(form.custo_estimado - form.valor)} por entrega.
                Pode ser proposital (atrair cliente), mas vale conferir.
              </span>
            </div>
          )}
        </div>
      </FormDialog>

    </div>
  )
}
