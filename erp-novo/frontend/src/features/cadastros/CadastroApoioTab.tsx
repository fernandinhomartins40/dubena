import { useState } from 'react'
import { Plus, Pencil, Trash2, List } from 'lucide-react'
import {
  Button, Badge, DataTable, type Column, EmptyState, Field, Input, CheckboxField,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useCadastro, useSalvarCadastro, useExcluirCadastro, type CadastroRow } from './api'

/** Definição de um campo extra do cadastro (além de descricao/ativo). */
export interface CampoExtra {
  campo: string
  label: string
  tipo: 'texto' | 'bool' | 'select'
  opcoes?: { v: string; l: string }[]
  /** como exibir o valor na tabela (badge/texto). default: texto */
  coluna?: (row: CadastroRow) => React.ReactNode
}

/**
 * Aba/painel genérico de cadastro de apoio (DataTable + Dialog de criar/editar).
 * Reusado em Configurações de Clientes/Financeiro. `tipo` casa com o backend.
 */
export function CadastroApoioTab({
  tipo, titulo, extras = [], podeEditar = true,
}: {
  tipo: string; titulo: string; extras?: CampoExtra[]; podeEditar?: boolean
}) {
  const { data, isLoading } = useCadastro(tipo)
  const salvar = useSalvarCadastro(tipo)
  const excluir = useExcluirCadastro(tipo)
  const [edit, setEdit] = useState<Partial<CadastroRow> | null>(null)
  const [del, setDel] = useState<CadastroRow | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.toString().trim()) { toast.error('Informe a descrição.'); return }
    const payload: Record<string, unknown> = { id: edit.id, descricao: edit.descricao, ativo: edit.ativo !== 0 }
    extras.forEach((e) => { payload[e.campo] = e.tipo === 'bool' ? (edit as any)[e.campo] === 1 || (edit as any)[e.campo] === true : (edit as any)[e.campo] })
    try { await salvar.mutateAsync(payload as any); toast.success(`${titulo} salvo.`); setEdit(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<CadastroRow>[] = [
    { key: 'descricao', header: 'Descrição', cell: (r) => <span className="font-medium">{r.descricao}</span> },
    ...extras.map((e): Column<CadastroRow> => ({
      key: e.campo, header: e.label,
      cell: (r) => e.coluna ? e.coluna(r) : (e.tipo === 'bool' ? (Number(r[e.campo]) ? 'Sim' : 'Não') : String(r[e.campo] ?? '—')),
    })),
    { key: 'ativo', header: 'Status', width: 'w-28', cell: (r) => r.ativo ? <Badge variant="success">Ativo</Badge> : <Badge variant="secondary">Inativo</Badge> },
    ...(podeEditar ? [{
      key: 'acoes', header: '', align: 'right' as const, width: 'w-24',
      cell: (r: CadastroRow) => (
        <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
          <Button variant="ghost" size="icon" onClick={() => setEdit(r)}><Pencil size={16} /></Button>
          <Button variant="ghost" size="icon" onClick={() => setDel(r)}><Trash2 size={16} /></Button>
        </div>
      ),
    }] : []),
  ]

  function novo() {
    const base: Partial<CadastroRow> = { ativo: 1 }
    extras.forEach((e) => { if (e.tipo === 'bool') (base as any)[e.campo] = 0 })
    setEdit(base)
  }

  return (
    <>
      {podeEditar && <div className="mb-3 flex justify-end"><Button onClick={novo}><Plus size={16} /> Novo</Button></div>}
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id}
        onRowClick={podeEditar ? (r) => setEdit(r) : undefined}
        empty={<EmptyState icon={<List />} title={`Nenhum registro em ${titulo}`} />} />

      <Dialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>{edit?.id ? `Editar ${titulo}` : `Novo ${titulo}`}</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <Field label="Descrição" required><Input autoFocus value={String(edit?.descricao ?? '')} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
            {extras.map((ex) => (
              <Field key={ex.campo} label={ex.label}>
                {ex.tipo === 'bool' ? (
                  <CheckboxField label={ex.label} checked={(edit as any)?.[ex.campo] === 1 || (edit as any)?.[ex.campo] === true} onChange={(b) => setEdit((s) => ({ ...s, [ex.campo]: b ? 1 : 0 }))} />
                ) : ex.tipo === 'select' ? (
                  <Select value={String((edit as any)?.[ex.campo] ?? '')} onValueChange={(v) => setEdit((s) => ({ ...s, [ex.campo]: v }))}>
                    <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
                    <SelectContent>{(ex.opcoes ?? []).map((o) => <SelectItem key={o.v} value={o.v}>{o.l}</SelectItem>)}</SelectContent>
                  </Select>
                ) : (
                  <Input value={String((edit as any)?.[ex.campo] ?? '')} onChange={(e) => setEdit((s) => ({ ...s, [ex.campo]: e.target.value }))} />
                )}
              </Field>
            ))}
            <CheckboxField label="Ativo" checked={edit?.ativo !== 0} onChange={(b) => setEdit((s) => ({ ...s, ativo: b ? 1 : 0 }))} />
          </div>
          <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={salvar.isPending} onClick={onSalvar}>Salvar</Button></DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={!!del} onOpenChange={(o) => !o && setDel(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>Excluir registro</DialogTitle></DialogHeader>
          <p className="text-sm text-muted-foreground">Excluir <strong>{del?.descricao}</strong>?</p>
          <DialogFooter>
            <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
            <Button variant="destructive" loading={excluir.isPending} onClick={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Excluído.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }}><Trash2 size={16} /> Excluir</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
