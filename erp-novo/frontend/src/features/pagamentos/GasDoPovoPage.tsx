import { useState } from 'react'
import { Plus, HandCoins, Wallet } from 'lucide-react'
import {
  Button, Input, Badge, type Column, Field, AsyncSelect,
  ResourceList, FormDialog, toast, PageHeader,
  Tabs, TabsList, TabsTrigger, TabsContent,
} from '@/components/ui'
import { brl } from '@/lib/format'
import { useBeneficios, useRegistrarBeneficio, useSacarBeneficio, type Beneficio } from './api'
import {
  GasDoPovoProgramaTab, GasDoPovoBeneficiariosTab, GasDoPovoVendasTab,
} from './GasDoPovoProgramaTab'

function BeneficiosVoucherTab() {
  const { data, isLoading } = useBeneficios()
  const registrar = useRegistrarBeneficio()
  const sacar = useSacarBeneficio()

  const [open, setOpen] = useState(false)
  const [form, setForm] = useState<Record<string, any>>({ competencia: new Date().toISOString().slice(0, 7) })
  const [clienteLabel, setClienteLabel] = useState('')

  const [saqueOpen, setSaqueOpen] = useState(false)
  const [alvo, setAlvo] = useState<Beneficio | null>(null)
  const [pedidoId, setPedidoId] = useState<number | null>(null)
  const [contaId, setContaId] = useState<number | null>(null)
  const [contaLabel, setContaLabel] = useState('')

  function abrir() { setForm({ competencia: new Date().toISOString().slice(0, 7) }); setClienteLabel(''); setOpen(true) }
  function abrirSaque(b: Beneficio) { setAlvo(b); setPedidoId(null); setContaId(null); setContaLabel(''); setSaqueOpen(true) }

  async function onRegistrar() {
    if (!form.valor) { toast.error('Informe o valor.'); return }
    try { await registrar.mutateAsync(form); toast.success('Benefício registrado.'); setOpen(false) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao registrar.') }
  }
  async function onSacar() {
    if (!alvo || !pedidoId) { toast.error('Informe o pedido.'); return }
    try { await sacar.mutateAsync({ id: alvo.id, data: { pedido_id: pedidoId, conta_id: contaId } }); toast.success('Benefício sacado.'); setSaqueOpen(false) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao sacar.') }
  }

  const columns: Column<Beneficio>[] = [
    { key: 'competencia', header: 'Competência', cell: (v) => <span className="font-medium tabular-nums">{v.competencia}</span> },
    { key: 'nis', header: 'NIS', cell: (v) => v.nis || '—' },
    { key: 'valor', header: 'Valor', align: 'right', cell: (v) => <span className="tabular-nums">{brl(v.valor)}</span> },
    {
      key: 'sit', header: 'Situação', cell: (v) => v.situacao === 'utilizado'
        ? <Badge variant="secondary">Utilizado</Badge>
        : v.situacao === 'cancelado' ? <Badge variant="destructive">Cancelado</Badge> : <Badge variant="success">Disponível</Badge>,
    },
    {
      key: 'acoes', header: '', align: 'right', cell: (v) => v.situacao === 'disponivel'
        ? <Button variant="secondary" size="sm" onClick={() => abrirSaque(v)}><Wallet size={15} /> Sacar</Button>
        : null,
    },
  ]

  return (
    <>
      <ResourceList
        title="Benefícios"
        subtitle="Modelo de voucher: crédito por competência, consumido no saque. Alimentado pela operação — o histórico do legado não usa este modelo."
        action={<Button onClick={abrir}><Plus size={16} /> Novo benefício</Button>}
        columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        emptyIcon={<HandCoins />} emptyTitle="Nenhum benefício"
      />

      <FormDialog
        open={open} onOpenChange={setOpen}
        title="Novo benefício" confirmLabel="Registrar"
        loading={registrar.isPending} onConfirm={onRegistrar}
      >
        <Field label="Cliente">
          <AsyncSelect endpoint="/lookups/clientes" value={form.cliente_id ?? null} valueLabel={clienteLabel}
            onChange={(id, opt) => { setForm((f) => ({ ...f, cliente_id: id })); setClienteLabel(opt?.label ?? '') }} />
        </Field>
        <div className="grid grid-cols-3 gap-3">
          <Field label="NIS"><Input value={form.nis ?? ''} onChange={(e) => setForm((f) => ({ ...f, nis: e.target.value }))} /></Field>
          <Field label="Competência" required><Input type="month" value={form.competencia ?? ''} onChange={(e) => setForm((f) => ({ ...f, competencia: e.target.value }))} /></Field>
          <Field label="Valor (R$)" required><Input type="number" step="0.01" min={0} value={form.valor ?? ''} onChange={(e) => setForm((f) => ({ ...f, valor: e.target.value }))} /></Field>
        </div>
      </FormDialog>

      <FormDialog
        open={saqueOpen} onOpenChange={setSaqueOpen}
        title={`Sacar benefício — ${alvo ? brl(alvo.valor) : ''}`}
        confirmLabel="Sacar" loading={sacar.isPending} onConfirm={onSacar}
      >
        <Field label="Pedido (nº)" required>
          <Input type="number" min={1} value={pedidoId ?? ''} onChange={(e) => setPedidoId(e.target.value === '' ? null : Number(e.target.value))} placeholder="ID do pedido" />
        </Field>
        <Field label="Conta (crédito do auxílio)">
          <AsyncSelect endpoint="/lookups/contas" value={contaId} valueLabel={contaLabel}
            onChange={(id, opt) => { setContaId(id); setContaLabel(opt?.label ?? '') }} />
        </Field>
      </FormDialog>
    </>
  )
}

/**
 * Gás do Povo.
 *
 * Duas leituras do mesmo programa, e a distinção é o que faz a tela ser útil:
 *
 *  - **Programa / Beneficiários / Vendas** — como o legado opera de verdade
 *    (parâmetros na config, checkbox no cliente, venda marcada por condição de
 *    pagamento). É onde estão os 821 beneficiários e as 1.003 vendas migradas.
 *  - **Benefícios** — o modelo de voucher (saldo + saque), que só o sistema novo
 *    tem. Fica vazio até a operação começar a usá-lo, e isso é o esperado.
 *
 * Ver `docs/02-auditoria-legado/GAS_DO_POVO_NO_LEGADO.md`.
 */
export function GasDoPovoPage() {
  const [aba, setAba] = useState('programa')

  return (
    <div>
      <PageHeader
        title="Gás do Povo"
        subtitle="Programa subsidiado — parâmetros, beneficiários e vendas"
      />
      <Tabs value={aba} onValueChange={setAba}>
        <TabsList>
          <TabsTrigger value="programa">Programa</TabsTrigger>
          <TabsTrigger value="beneficiarios">Beneficiários</TabsTrigger>
          <TabsTrigger value="vendas">Vendas</TabsTrigger>
          <TabsTrigger value="beneficios">Benefícios</TabsTrigger>
        </TabsList>
        <TabsContent value="programa"><GasDoPovoProgramaTab /></TabsContent>
        <TabsContent value="beneficiarios"><GasDoPovoBeneficiariosTab /></TabsContent>
        <TabsContent value="vendas"><GasDoPovoVendasTab /></TabsContent>
        <TabsContent value="beneficios"><BeneficiosVoucherTab /></TabsContent>
      </Tabs>
    </div>
  )
}
