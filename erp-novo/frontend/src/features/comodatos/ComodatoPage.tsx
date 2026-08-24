import { useState } from 'react'
import { Plus, PackageCheck, Undo2, FileSignature } from 'lucide-react'
import {
  Button, Input, Badge, type Column, Field, AsyncSelect, Can,
  ResourceList, FormDialog, toast,
} from '@/components/ui'
import { data as fmtData } from '@/lib/format'
import { useComodatos, useCriarComodato, abrirContratoComodato, type Comodato } from './api'
import { mensagemDeErroBlob } from '@/lib/pdf'
import { DevolucaoDialog } from './DevolucaoDialog'
import { ComodatoDetalhe } from './ComodatoDetalhe'

/** Situações que encerram o comodato — nenhuma delas descreve posse vigente. */
const ENCERRADAS = ['DEVOLVIDO', 'ENCERRADO', 'CANCELADO']

export function ComodatoPage() {
  const { data, isLoading } = useComodatos()
  const criar = useCriarComodato()
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState<Record<string, any>>({ quantidade: 1 })
  const [clienteLabel, setClienteLabel] = useState('')
  const [prodLabel, setProdLabel] = useState('')
  const [aDevolver, setADevolver] = useState<Comodato | null>(null)
  const [detalhe, setDetalhe] = useState<number | null>(null)

  function abrir() { setForm({ quantidade: 1 }); setClienteLabel(''); setProdLabel(''); setOpen(true) }

  async function onCriar() {
    if (!form.cliente_id || !form.produto_id) { toast.error('Cliente e produto são obrigatórios.'); return }
    try { await criar.mutateAsync(form); toast.success('Comodato registrado.'); setOpen(false) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao registrar.') }
  }

  async function onContrato(v: Comodato) {
    try { await abrirContratoComodato(v.id) }
    catch (e: any) { toast.error(await mensagemDeErroBlob(e, 'Falha ao gerar o contrato.')) }
  }

  const columns: Column<Comodato>[] = [
    { key: 'cliente', header: 'Cliente', cell: (v) => v.cliente?.nome || '—' },
    { key: 'produto', header: 'Produto', cell: (v) => v.produto?.descricao || '—' },
    { key: 'qtd', header: 'Qtd', align: 'right', cell: (v) => <span className="tabular-nums">{Number(v.quantidade)}</span> },
    { key: 'dev', header: 'Devolvido', align: 'right', cell: (v) => <span className="tabular-nums">{Number(v.quantidade_devolvida)}</span> },
    {
      // A coluna que faltava: é o saldo em poder do cliente que decide se há
      // devolução a fazer, e ele não estava em lugar nenhum da listagem.
      key: 'posse', header: 'Em posse', align: 'right',
      cell: (v) => {
        const posse = Number(v.quantidade) - Number(v.quantidade_devolvida)
        return <span className={`tabular-nums ${posse > 0 ? 'font-medium' : 'text-muted-foreground'}`}>{posse}</span>
      },
    },
    { key: 'desde', header: 'Desde', cell: (v) => fmtData(v.data_emprestimo) },
    { key: 'sit', header: 'Situação', cell: (v) => <SituacaoBadge situacao={v.situacao} /> },
    {
      key: 'acoes', header: '', align: 'right', width: 'w-60',
      cell: (v) => {
        // Encerrado nao gera contrato: o papel afirmaria uma posse que nao
        // existe mais, e serviria de base para cobranca indevida. O detalhe
        // continua acessivel — o historico e o que se consulta depois.
        if (ENCERRADAS.includes(v.situacao)) return null

        return (
          <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
            <Button variant="outline" size="sm" onClick={() => onContrato(v)}>
              <FileSignature size={15} /> Contrato
            </Button>
            <Can permission="comodato.edit">
              <Button variant="ghost" size="sm" onClick={() => setADevolver(v)}>
                <Undo2 size={15} /> Devolver
              </Button>
            </Can>
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
        action={<Can permission="comodato.edit"><Button onClick={abrir}><Plus size={16} /> Novo comodato</Button></Can>}
        columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        onRowClick={(v) => setDetalhe(v.id)}
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
        <Field label="Quantidade" required>
          <Input type="number" min={1} value={form.quantidade ?? 1}
            onChange={(e) => setForm((f) => ({ ...f, quantidade: Number(e.target.value) }))} />
        </Field>
        <Field label="Representante (quem assina)">
          <Input value={form.nome_representante ?? ''}
            onChange={(e) => setForm((f) => ({ ...f, nome_representante: e.target.value }))} />
        </Field>
        <Field label="CPF do representante">
          <Input value={form.cpf_representante ?? ''}
            onChange={(e) => setForm((f) => ({ ...f, cpf_representante: e.target.value }))} />
        </Field>
      </FormDialog>

      <DevolucaoDialog comodato={aDevolver} onOpenChange={(v) => !v && setADevolver(null)} />
      <ComodatoDetalhe id={detalhe} onOpenChange={(v) => !v && setDetalhe(null)} />
    </>
  )
}

function SituacaoBadge({ situacao }: { situacao: string }) {
  if (situacao === 'PARCIAL') return <Badge variant="warning">Devolução parcial</Badge>
  if (situacao === 'CANCELADO') return <Badge variant="destructive">Cancelado</Badge>
  if (ENCERRADAS.includes(situacao)) return <Badge variant="secondary">Devolvido</Badge>
  return <Badge variant="warning">Em aberto</Badge>
}
