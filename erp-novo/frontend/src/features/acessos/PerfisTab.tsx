import { useMemo, useState } from 'react'
import { Plus, ShieldCheck } from 'lucide-react'
import {
  Button, Input, Badge, Field, type Column,
  ResourceList, FormDialog, RowActions, Checkbox, toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import {
  usePapeis, useSalvarPapel, useExcluirPapel, useCatalogoPermissoes, type Papel,
} from './api'

/**
 * Perfis (papéis) da Central de Acessos. CRUD de papéis do grupo + marcação de
 * permissões a partir do catálogo (fonte da verdade no backend). A própria UI
 * filtra ações por `can()`, mas a AUTORIDADE é o backend (Gate central — A1).
 */
export function PerfisTab() {
  const { data, isLoading } = usePapeis()
  const { data: catalogo } = useCatalogoPermissoes()
  const salvar = useSalvarPapel()
  const excluir = useExcluirPapel()
  const { can } = useAuth()
  const podeEditar = can('papel.edit') || can('papel.create')

  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState<Papel | null>(null)
  const [nome, setNome] = useState('')
  const [descricao, setDescricao] = useState('')
  const [marcadas, setMarcadas] = useState<Set<string>>(new Set())

  const modulos = useMemo(() => Object.entries(catalogo ?? {}), [catalogo])

  function abrir(reg?: Papel) {
    setEdit(reg ?? null)
    setNome(reg?.nome ?? '')
    setDescricao(reg?.descricao ?? '')
    setMarcadas(new Set(reg?.permissoes ?? []))
    setOpen(true)
  }

  function toggle(chave: string) {
    setMarcadas((s) => {
      const n = new Set(s)
      n.has(chave) ? n.delete(chave) : n.add(chave)
      return n
    })
  }

  function toggleModulo(itens: { chave: string }[], marcar: boolean) {
    setMarcadas((s) => {
      const n = new Set(s)
      itens.forEach((i) => (marcar ? n.add(i.chave) : n.delete(i.chave)))
      return n
    })
  }

  async function onSalvar() {
    try {
      await salvar.mutateAsync({
        id: edit?.id ?? null,
        data: { nome, descricao: descricao || null, permissoes: [...marcadas] },
      })
      toast.success('Perfil salvo.')
      setOpen(false)
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao salvar perfil.')
    }
  }

  const columns: Column<Papel>[] = [
    { key: 'nome', header: 'Perfil', cell: (v) => <span className="font-medium">{v.nome}</span> },
    { key: 'descricao', header: 'Descrição', cell: (v) => v.descricao || '—' },
    { key: 'perms', header: 'Permissões', cell: (v) => <Badge variant="secondary">{v.permissoes.length}</Badge> },
    { key: 'usuarios', header: 'Usuários', cell: (v) => <Badge variant="secondary">{v.usuarios_count}</Badge> },
    {
      key: 'acoes', header: '', align: 'right',
      cell: (v) => (
        <RowActions
          onEdit={podeEditar ? () => abrir(v) : undefined}
          onDelete={can('papel.delete') ? () => excluir.mutate(v.id) : undefined}
          confirmMsg="Excluir este perfil?"
        />
      ),
    },
  ]

  return (
    <>
      <ResourceList
        title="Perfis"
        subtitle="Papéis do grupo e suas permissões"
        action={can('papel.create') ? <Button onClick={() => abrir()}><Plus size={16} /> Novo perfil</Button> : undefined}
        columns={columns}
        rows={data}
        loading={isLoading}
        rowKey={(v) => v.id}
        emptyIcon={<ShieldCheck />}
        emptyTitle="Nenhum perfil"
        emptyDescription="Crie perfis para agrupar permissões e atribuir a usuários."
      />

      <FormDialog
        open={open} onOpenChange={setOpen}
        title={edit ? 'Editar perfil' : 'Novo perfil'}
        loading={salvar.isPending} onConfirm={onSalvar}
        widthClass="max-w-3xl"
      >
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Nome" required><Input value={nome} onChange={(e) => setNome(e.target.value)} /></Field>
          <Field label="Descrição"><Input value={descricao} onChange={(e) => setDescricao(e.target.value)} /></Field>
        </div>

        <div className="mt-2">
          <p className="text-sm font-medium mb-2">Permissões</p>
          <div className="max-h-[50vh] overflow-y-auto space-y-4 rounded-md border border-border p-3">
            {modulos.map(([modulo, itens]) => {
              const todasMarcadas = itens.every((i) => marcadas.has(i.chave))
              return (
                <div key={modulo}>
                  <div className="flex items-center justify-between mb-1.5">
                    <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{modulo}</span>
                    <button
                      type="button"
                      className="text-xs text-primary hover:underline"
                      onClick={() => toggleModulo(itens, !todasMarcadas)}
                    >
                      {todasMarcadas ? 'Limpar' : 'Marcar tudo'}
                    </button>
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1.5">
                    {itens.map((i) => (
                      <label key={i.chave} className="flex items-center gap-2 text-sm cursor-pointer">
                        <Checkbox checked={marcadas.has(i.chave)} onCheckedChange={() => toggle(i.chave)} />
                        <span>{i.descricao}</span>
                      </label>
                    ))}
                  </div>
                </div>
              )
            })}
          </div>
        </div>
      </FormDialog>
    </>
  )
}
