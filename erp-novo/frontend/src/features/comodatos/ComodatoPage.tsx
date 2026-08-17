import { useState } from 'react'
import { Plus, PackageCheck, Undo2, FileSignature } from 'lucide-react'
import {
  Button, Input, Badge, type Column, Field, AsyncSelect,
  ResourceList, FormDialog, toast,
} from '@/components/ui'
import { data as fmtData } from '@/lib/format'
import { useComodatos, useCriarComodato, useDevolverComodato, abrirContratoComodato, type Comodato } from './api'
import { mensagemDeErroBlob } from '@/lib/pdf'

export function ComodatoPage() {
  const { data, isLoading } = useComodatos()
  const criar = useCriarComodato()
  const devolver = useDevolverComodato()
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState<Record<string, any>>({ quantidade: 1 })
  const [clienteLabel, setClienteLabel] = useState('')
  const [prodLabel, setProdLabel] = useState('')

  function abrir() { setForm({ quantidade: 1 }); setClienteLabel(''); setProdLabel(''); setOpen(true) }
  async function onCriar() {
    if (!form.cliente_id || !form.produto_id) { toast.error('Cliente e produto são obrigatórios.'); return }
    try { await criar.mutateAsync(form); toast.success('Comodato registrado.'); setOpen(false) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao registrar.') }
  }
  async function onDevolver(c: Comodato) {
    const pend = Number(c.quantidade) - Number(c.quantidade_devolvida)
    const q = prompt(`Quantidade a devolver (pendente: ${pend})`, String(pend))
    if (q === null) return
    try { await devolver.mutateAsync({ id: c.id, quantidade: Number(q) }); toast.success('Devolução registrada.') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') }
  }

  async function onContrato(v: Comodato) {
    try { await abrirContratoComodato(v.id) }
    catch (e: any) { toast.error(await mensagemDeErroBlob(e, 'Falha ao gerar o contrato.')) }
  }

  const columns: Column<Comodato>[] = [
    { key: 'cliente', header: 'Cliente', cell: (v) => v.cliente?.nome || '—' },
    { key: 'produto', header: 'Produto', cell: (v) => v.produto?.descricao || '—' },
    { key: 'qtd', header: 'Qtd', align: 'right', cell: (v) => <span className="tabular-nums">{v.quantidade}</span> },
    { key: 'dev', header: 'Devolvido', align: 'right', cell: (v) => <span className="tabular-nums">{v.quantidade_devolvida}</span> },
    { key: 'desde', header: 'Desde', cell: (v) => fmtData(v.data_emprestimo) },
    { key: 'sit', header: 'Situação', cell: (v) => v.situacao === 'DEVOLVIDO' ? <Badge variant="secondary">Devolvido</Badge> : <Badge variant="warning">Em aberto</Badge> },
    {
      key: 'acoes', header: '', align: 'right', width: 'w-60',
      cell: (v) => {
        // Devolvido nao gera contrato: o papel afirmaria uma posse que nao
        // existe mais, e serviria de base para cobranca indevida.
        if (v.situacao === 'DEVOLVIDO') return null

        return (
          <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
            <Button variant="outline" size="sm" onClick={() => onContrato(v)}>
              <FileSignature size={15} /> Contrato
            </Button>
            <Button variant="ghost" size="sm" onClick={() => onDevolver(v)}>
              <Undo2 size={15} /> Devolver
            </Button>
          </div>
        )
      },
    },
  ]

  return (
    <>
      <ResourceList
        title="Comodatos"
        subtitle="Vasilhames emprestados a clientes"
        action={<Button onClick={abrir}><Plus size={16} /> Novo comodato</Button>}
        columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        emptyIcon={<PackageCheck />} emptyTitle="Nenhum comodato"
      />

      <FormDialog
        open={open} onOpenChange={setOpen}
        title="Novo comodato" confirmLabel="Registrar"
        loading={criar.isPending} onConfirm={onCriar}
      >
        <Field label="Cliente" required>
          <AsyncSelect endpoint="/lookups/clientes" value={form.cliente_id ?? null} valueLabel={clienteLabel}
            onChange={(id, opt) => { setForm((f) => ({ ...f, cliente_id: id })); setClienteLabel(opt?.label ?? '') }} />
        </Field>
        <Field label="Produto (vasilhame)" required>
          <AsyncSelect endpoint="/lookups/produtos-vasilhame" value={form.produto_id ?? null} valueLabel={prodLabel}
            onChange={(id, opt) => { setForm((f) => ({ ...f, produto_id: id })); setProdLabel(opt?.label ?? '') }} />
        </Field>
        <Field label="Quantidade" required><Input type="number" min={1} value={form.quantidade ?? 1} onChange={(e) => setForm((f) => ({ ...f, quantidade: Number(e.target.value) }))} /></Field>
      </FormDialog>
    </>
  )
}
