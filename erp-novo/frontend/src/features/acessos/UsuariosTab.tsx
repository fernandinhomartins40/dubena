import { useState } from 'react'
import { Plus, Users, KeyRound } from 'lucide-react'
import {
  Button, Input, Badge, Field, type Column, CheckboxField, Checkbox,
  ResourceList, FormDialog, RowActions, toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import {
  useUsuarios, useSalvarUsuario, useExcluirUsuario, useResetarSenha,
  usePapeis, type Usuario,
} from './api'

/**
 * Usuários da Central de Acessos. CRUD de usuários da EMPRESA ativa + atribuição
 * de papéis (escopados na empresa) + reset de senha. A autoridade é o backend;
 * a UI só filtra ações por `can()`.
 */
export function UsuariosTab() {
  const { data, isLoading } = useUsuarios()
  const { data: papeis } = usePapeis()
  const salvar = useSalvarUsuario()
  const excluir = useExcluirUsuario()
  const resetar = useResetarSenha()
  const { can, user: atual } = useAuth()

  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState<Usuario | null>(null)
  const [form, setForm] = useState<Record<string, any>>({})
  const [papeisSel, setPapeisSel] = useState<Set<number>>(new Set())

  // Reset de senha (modal próprio).
  const [resetOpen, setResetOpen] = useState(false)
  const [resetAlvo, setResetAlvo] = useState<Usuario | null>(null)
  const [senha, setSenha] = useState('')
  const [senhaConf, setSenhaConf] = useState('')

  function abrir(reg?: Usuario) {
    setEdit(reg ?? null)
    setForm(reg ? { name: reg.name, email: reg.email, ativo: reg.ativo } : { ativo: true })
    setPapeisSel(new Set(reg?.papeis.map((p) => p.id) ?? []))
    setOpen(true)
  }

  function togglePapel(id: number) {
    setPapeisSel((s) => {
      const n = new Set(s)
      n.has(id) ? n.delete(id) : n.add(id)
      return n
    })
  }

  async function onSalvar() {
    try {
      const payload: Record<string, unknown> = {
        name: form.name, email: form.email, papeis: [...papeisSel],
      }
      if (edit) {
        payload.ativo = form.ativo
      } else {
        payload.password = form.password
        payload.password_confirmation = form.password_confirmation
      }
      await salvar.mutateAsync({ id: edit?.id ?? null, data: payload })
      toast.success('Usuário salvo.')
      setOpen(false)
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao salvar usuário.')
    }
  }

  function abrirReset(u: Usuario) {
    setResetAlvo(u); setSenha(''); setSenhaConf(''); setResetOpen(true)
  }

  async function onResetar() {
    if (!resetAlvo) return
    try {
      await resetar.mutateAsync({ id: resetAlvo.id, data: { password: senha, password_confirmation: senhaConf } })
      toast.success('Senha redefinida.')
      setResetOpen(false)
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao redefinir senha.')
    }
  }

  const columns: Column<Usuario>[] = [
    { key: 'name', header: 'Nome', cell: (v) => <span className="font-medium">{v.name}</span> },
    { key: 'email', header: 'E-mail', cell: (v) => v.email },
    {
      key: 'papeis', header: 'Perfis',
      cell: (v) => v.support
        ? <Badge variant="default">Suporte</Badge>
        : v.papeis.length
          ? <div className="flex flex-wrap gap-1">{v.papeis.map((p) => <Badge key={p.id} variant="secondary">{p.nome}</Badge>)}</div>
          : '—',
    },
    { key: 'ativo', header: 'Status', cell: (v) => v.ativo ? <Badge variant="success">Ativo</Badge> : <Badge variant="secondary">Inativo</Badge> },
    {
      key: 'acoes', header: '', align: 'right',
      cell: (v) => (
        <RowActions
          extra={can('usuario.reset') && !v.support ? (
            <Button variant="ghost" size="icon" aria-label="Resetar senha" onClick={() => abrirReset(v)}>
              <KeyRound size={15} />
            </Button>
          ) : undefined}
          onEdit={can('usuario.edit') && !v.support ? () => abrir(v) : undefined}
          onDelete={can('usuario.delete') && !v.support && v.id !== atual?.id ? () => excluir.mutate(v.id) : undefined}
          confirmMsg="Excluir este usuário?"
        />
      ),
    },
  ]

  return (
    <>
      <ResourceList
        title="Usuários"
        subtitle="Acesso à empresa ativa, perfis e senha"
        action={can('usuario.create') ? <Button onClick={() => abrir()}><Plus size={16} /> Novo usuário</Button> : undefined}
        columns={columns}
        rows={data}
        loading={isLoading}
        rowKey={(v) => v.id}
        emptyIcon={<Users />}
        emptyTitle="Nenhum usuário"
        emptyDescription="Cadastre usuários e atribua perfis de acesso."
      />

      <FormDialog
        open={open} onOpenChange={setOpen}
        title={edit ? 'Editar usuário' : 'Novo usuário'}
        loading={salvar.isPending} onConfirm={onSalvar}
        widthClass="max-w-2xl"
      >
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Nome" required><Input value={form.name ?? ''} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} /></Field>
          <Field label="E-mail" required><Input type="email" value={form.email ?? ''} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} /></Field>
        </div>

        {!edit && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Field label="Senha" required><Input type="password" value={form.password ?? ''} onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))} /></Field>
            <Field label="Confirmar senha" required><Input type="password" value={form.password_confirmation ?? ''} onChange={(e) => setForm((f) => ({ ...f, password_confirmation: e.target.value }))} /></Field>
          </div>
        )}

        {edit && <CheckboxField label="Usuário ativo" checked={!!form.ativo} onChange={(c) => setForm((f) => ({ ...f, ativo: c }))} />}

        <div>
          <p className="text-sm font-medium mb-2">Perfis</p>
          {(papeis ?? []).length === 0
            ? <p className="text-sm text-muted-foreground">Nenhum perfil cadastrado ainda — crie um na aba “Perfis”.</p>
            : (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-1.5 rounded-md border border-border p-3 max-h-56 overflow-y-auto">
                {(papeis ?? []).map((p) => (
                  <label key={p.id} className="flex items-center gap-2 text-sm cursor-pointer">
                    <Checkbox checked={papeisSel.has(p.id)} onCheckedChange={() => togglePapel(p.id)} />
                    <span>{p.nome}</span>
                  </label>
                ))}
              </div>
            )}
        </div>
      </FormDialog>

      <FormDialog
        open={resetOpen} onOpenChange={setResetOpen}
        title={`Resetar senha — ${resetAlvo?.name ?? ''}`}
        confirmLabel="Redefinir"
        loading={resetar.isPending} onConfirm={onResetar}
      >
        <p className="text-sm text-muted-foreground">A nova senha terá efeito imediato e os tokens de app do usuário serão revogados.</p>
        <Field label="Nova senha" required><Input type="password" value={senha} onChange={(e) => setSenha(e.target.value)} /></Field>
        <Field label="Confirmar nova senha" required><Input type="password" value={senhaConf} onChange={(e) => setSenhaConf(e.target.value)} /></Field>
      </FormDialog>
    </>
  )
}
