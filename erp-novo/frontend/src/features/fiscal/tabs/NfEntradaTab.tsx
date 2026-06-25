import { useState } from 'react'
import { FileText } from 'lucide-react'
import {
  Button, Badge, DataTable, type Column, EmptyState, Field, AsyncSelect, Textarea, FormDialog, toast,
} from '@/components/ui'
import { useNfEntrada, useImportarNfEntrada, useProcessarNfEntrada, type NfEntradaRow } from '../api'
import { brl, dataHora as fmtData } from '@/lib/format'

/** F06 — NF de Entrada: importa o XML do fornecedor e processa (estoque + CP). */
export function NfEntradaTab() {
  const { data, isLoading } = useNfEntrada()
  const importar = useImportarNfEntrada()
  const processar = useProcessarNfEntrada()
  const [xml, setXml] = useState('')
  const [openImport, setOpenImport] = useState(false)
  const [proc, setProc] = useState<NfEntradaRow | null>(null)
  const [setorId, setSetorId] = useState<number | null>(null)
  const [setorLabel, setSetorLabel] = useState<string | null>(null)

  async function onImportar() {
    if (!xml.trim()) { toast.error('Cole o XML da NF.'); return }
    try {
      const nota = await importar.mutateAsync(xml)
      toast.success(`NF ${nota.numero} importada.`); setXml(''); setOpenImport(false)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao importar o XML.') }
  }

  async function onProcessar() {
    if (!proc || !setorId) { toast.error('Selecione o setor de destino.'); return }
    try {
      await processar.mutateAsync({ id: proc.id, setor_id: setorId })
      toast.success('NF processada: estoque e contas a pagar gerados.'); setProc(null); setSetorId(null); setSetorLabel(null)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao processar a NF.') }
  }

  const columns: Column<NfEntradaRow>[] = [
    { key: 'numero', header: 'Número', cell: (r) => <span className="tabular-nums">{r.numero ?? '—'}</span> },
    { key: 'emitente', header: 'Emitente', cell: (r) => <span className="font-medium">{r.emitente_nome ?? '—'}</span> },
    { key: 'emissao', header: 'Emissão', cell: (r) => fmtData(r.data_emissao) },
    { key: 'valor', header: 'Valor', align: 'right', cell: (r) => <span className="tabular-nums">{brl(r.valor_total)}</span> },
    { key: 'situacao', header: 'Situação', cell: (r) => r.situacao === 'processada'
      ? <Badge variant="success">Processada</Badge> : <Badge variant="warning">Importada</Badge> },
    { key: 'acoes', header: '', align: 'right', width: 'w-28', cell: (r) => r.situacao !== 'processada'
      ? <Button size="sm" variant="outline" onClick={() => setProc(r)}>Processar</Button> : null },
  ]

  return (
    <>
      <div className="flex justify-end mb-3">
        <Button onClick={() => setOpenImport(true)}><FileText size={16} /> Importar XML</Button>
      </div>

      <DataTable
        columns={columns}
        rows={data?.data}
        loading={isLoading}
        rowKey={(r) => r.id}
        empty={<EmptyState icon={<FileText />} title="Nenhuma NF de entrada" description="Importe o XML de uma NF do fornecedor." />}
      />

      {/* Importar XML */}
      <FormDialog open={openImport} onOpenChange={setOpenImport} title="Importar NF de Entrada (XML)"
        confirmLabel="Importar" loading={importar.isPending} onConfirm={onImportar}>
        <Field label="XML da NF-e">
          <Textarea rows={10} value={xml} onChange={(e) => setXml(e.target.value)} placeholder="Cole aqui o conteúdo do XML…" />
        </Field>
      </FormDialog>

      {/* Processar (escolher setor) */}
      <FormDialog open={proc !== null} onOpenChange={(o) => !o && setProc(null)} title={`Processar NF ${proc?.numero ?? ''}`}
        confirmLabel="Processar" loading={processar.isPending} onConfirm={onProcessar}>
        <p className="text-sm text-muted-foreground">Dá entrada no estoque do setor escolhido e gera o contas a pagar ao fornecedor.</p>
        <Field label="Setor de destino" required>
          <AsyncSelect endpoint="/lookups/setores" value={setorId} valueLabel={setorLabel}
            onChange={(id, option) => { setSetorId(id); setSetorLabel(option?.label ?? null) }} placeholder="Selecione o setor" />
        </Field>
      </FormDialog>
    </>
  )
}
