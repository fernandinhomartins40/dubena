import { useState } from 'react'
import { Plus, Pencil, Trash2, ListFilter } from 'lucide-react'
import {
  Button, Card, CardContent, Badge, DataTable, type Column, EmptyState, Field, Input,
  AsyncSelect, FormDialog, ConfirmDialog, toast,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
} from '@/components/ui'
import {
  useExtratoRegras, useSalvarExtratoRegra, useExcluirExtratoRegra, type ExtratoRegra,
} from '../api'

/**
 * Regras de classificação do extrato bancário (T4.2).
 *
 * Sem elas a importação de OFX devolve uma lista crua para classificar à mão.
 * Com elas, a linha do extrato volta na conciliação já pré-classificada.
 *
 * As regras são POR CONTA — "PIX RECEBIDO" significa coisas diferentes na conta
 * do banco e no caixa interno —, por isso a conta é escolhida antes de tudo.
 */
export function ExtratoRegrasTab() {
  const [contaId, setContaId] = useState<number | null>(null)
  const [contaLabel, setContaLabel] = useState<string | null>(null)
  const { data, isLoading } = useExtratoRegras(contaId)
  const salvar = useSalvarExtratoRegra(contaId)
  const excluir = useExcluirExtratoRegra(contaId)
  const [edit, setEdit] = useState<Partial<ExtratoRegra> | null>(null)
  const [del, setDel] = useState<ExtratoRegra | null>(null)
  const [labels, setLabels] = useState<Record<string, string | null>>({})

  const acoes = data?.acoes ?? []
  const rotulo = (v: string) => acoes.find((a) => a.valor === v)?.rotulo ?? v
  const transfere = edit?.acao === 'TRANSFERIR'

  function abrir(r?: ExtratoRegra) {
    setLabels({})
    setEdit(r ?? { acao: 'LANCAR', ativo: true, prioridade: 0 })
  }

  async function onSalvar() {
    if (!edit?.descricao?.trim()) { toast.error('Informe o texto que a regra procura no extrato.'); return }
    if (!edit.acao) { toast.error('Escolha a ação.'); return }
    try {
      // O backend valida por ação; aqui só evitamos mandar ids de uma ação na outra.
      const especificos = transfere
        ? { conta_origem_id: edit.conta_origem_id }
        : {
            condicaopagamento_id: edit.condicaopagamento_id,
            contamovimentotipo_id: edit.contamovimentotipo_id,
            plano_conta_id: edit.plano_conta_id,
            centro_custo_id: edit.centro_custo_id,
          }
      await salvar.mutateAsync({
        id: edit.id,
        descricao: edit.descricao,
        acao: edit.acao,
        cliente_id: edit.cliente_id ?? null,
        ativo: edit.ativo !== false,
        prioridade: Number(edit.prioridade ?? 0),
        ...especificos,
      })
      toast.success('Regra salva.')
      setEdit(null)
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao salvar a regra.')
    }
  }

  const columns: Column<ExtratoRegra>[] = [
    { key: 'prioridade', header: 'Prior.', width: 'w-20', cell: (r) => <span className="tabular-nums text-muted-foreground">{r.prioridade}</span> },
    { key: 'descricao', header: 'Casa com', cell: (r) => <span className="font-medium">{r.descricao}</span> },
    { key: 'acao', header: 'Ação', cell: (r) => <Badge variant="secondary">{rotulo(r.acao)}</Badge> },
    { key: 'ativo', header: 'Status', width: 'w-28', cell: (r) => r.ativo ? <Badge variant="success">Ativa</Badge> : <Badge variant="secondary">Inativa</Badge> },
    {
      key: 'acoes', header: '', align: 'right', width: 'w-24',
      cell: (r) => (
        <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
          <Button variant="ghost" size="icon" onClick={() => abrir(r)}><Pencil size={16} /></Button>
          <Button variant="ghost" size="icon" onClick={() => setDel(r)}><Trash2 size={16} /></Button>
        </div>
      ),
    },
  ]

  return (
    <>
      <Card className="mb-4"><CardContent className="pt-6 flex flex-wrap items-end gap-3">
        <div className="w-72">
          <Field label="Conta" required>
            <AsyncSelect endpoint="/lookups/contas" value={contaId} valueLabel={contaLabel}
              onChange={(id, o) => { setContaId(id); setContaLabel(o?.label ?? null) }} />
          </Field>
        </div>
        <Button disabled={!contaId} onClick={() => abrir()}><Plus size={16} /> Nova regra</Button>
        <p className="text-sm text-muted-foreground basis-full">
          Quando a descrição da linha do extrato contiver o texto da regra, a conciliação já devolve o
          lançamento pré-classificado. Regras mais específicas vencem as genéricas; a prioridade desempata.
        </p>
      </CardContent></Card>

      {contaId === null ? (
        <EmptyState icon={<ListFilter />} title="Escolha uma conta" description="As regras são cadastradas por conta bancária." />
      ) : (
        <DataTable columns={columns} rows={data?.data} loading={isLoading} rowKey={(r) => r.id}
          onRowClick={(r) => abrir(r)}
          empty={<EmptyState icon={<ListFilter />} title="Nenhuma regra nesta conta" description="Sem regras, o extrato importado volta sem classificação." />} />
      )}

      <FormDialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)}
        title={edit?.id ? 'Editar regra' : 'Nova regra'} loading={salvar.isPending} onConfirm={onSalvar}>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Casa com (texto do extrato)" required className="sm:col-span-2">
            <Input autoFocus placeholder="PIX RECEBIDO" value={edit?.descricao ?? ''}
              onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} />
          </Field>
          <Field label="Ação" required>
            <Select value={edit?.acao ?? ''} onValueChange={(v) => setEdit((s) => ({ ...s, acao: v }))}>
              <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
              <SelectContent>
                {acoes.map((a) => <SelectItem key={a.valor} value={a.valor}>{a.rotulo}</SelectItem>)}
              </SelectContent>
            </Select>
          </Field>
          <Field label="Prioridade" hint="Maior vence primeiro">
            <Input type="number" min={0} value={edit?.prioridade ?? 0}
              onChange={(e) => setEdit((s) => ({ ...s, prioridade: Number(e.target.value) }))} />
          </Field>

          {transfere ? (
            <Field label="Conta de origem" required className="sm:col-span-2">
              <AsyncSelect endpoint="/lookups/contas" value={edit?.conta_origem_id ?? null} valueLabel={labels.conta_origem ?? null}
                onChange={(id, o) => { setEdit((s) => ({ ...s, conta_origem_id: id })); setLabels((l) => ({ ...l, conta_origem: o?.label ?? null })) }} />
            </Field>
          ) : (
            <>
              <Field label="Condição de pagamento" required>
                <AsyncSelect endpoint="/lookups/condicoes-pagamento" value={edit?.condicaopagamento_id ?? null} valueLabel={labels.condicao ?? null}
                  onChange={(id, o) => { setEdit((s) => ({ ...s, condicaopagamento_id: id })); setLabels((l) => ({ ...l, condicao: o?.label ?? null })) }} />
              </Field>
              <Field label="Tipo de movimento" required>
                <AsyncSelect endpoint="/lookups/movimento-tipos" value={edit?.contamovimentotipo_id ?? null} valueLabel={labels.tipo ?? null}
                  onChange={(id, o) => { setEdit((s) => ({ ...s, contamovimentotipo_id: id })); setLabels((l) => ({ ...l, tipo: o?.label ?? null })) }} />
              </Field>
              <Field label="Plano de contas" required>
                <AsyncSelect endpoint="/lookups/planos-conta" value={edit?.plano_conta_id ?? null} valueLabel={labels.plano ?? null}
                  onChange={(id, o) => { setEdit((s) => ({ ...s, plano_conta_id: id })); setLabels((l) => ({ ...l, plano: o?.label ?? null })) }} />
              </Field>
              <Field label="Centro de custo" required>
                <AsyncSelect endpoint="/lookups/centros-custo" value={edit?.centro_custo_id ?? null} valueLabel={labels.centro ?? null}
                  onChange={(id, o) => { setEdit((s) => ({ ...s, centro_custo_id: id })); setLabels((l) => ({ ...l, centro: o?.label ?? null })) }} />
              </Field>
            </>
          )}

          <Field label="Cliente / fornecedor" hint="Opcional" className="sm:col-span-2">
            <AsyncSelect endpoint="/lookups/clientes-fornecedores" value={edit?.cliente_id ?? null} valueLabel={labels.cliente ?? null}
              onChange={(id, o) => { setEdit((s) => ({ ...s, cliente_id: id })); setLabels((l) => ({ ...l, cliente: o?.label ?? null })) }} />
          </Field>
          <Field label="Status">
            <Select value={edit?.ativo === false ? '0' : '1'} onValueChange={(v) => setEdit((s) => ({ ...s, ativo: v === '1' }))}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent><SelectItem value="1">Ativa</SelectItem><SelectItem value="0">Inativa</SelectItem></SelectContent>
            </Select>
          </Field>
        </div>
      </FormDialog>

      <ConfirmDialog open={!!del} onOpenChange={(o) => !o && setDel(null)} title="Excluir regra"
        description={<>Excluir a regra <strong>{del?.descricao}</strong>? O extrato volta a exigir classificação manual para essas linhas.</>}
        loading={excluir.isPending}
        onConfirm={async () => {
          try { await excluir.mutateAsync(del!.id); toast.success('Regra excluída.') }
          catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao excluir.') }
          finally { setDel(null) }
        }} />
    </>
  )
}
