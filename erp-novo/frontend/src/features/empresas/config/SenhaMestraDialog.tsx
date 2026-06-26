import { useState } from 'react'
import { KeyRound } from 'lucide-react'
import { Button, Field, Input, FormDialog, toast } from '@/components/ui'
import { useSenhaMestra } from '../api'

export function SenhaMestraDialog({ empresaId, tem }: { empresaId: number; tem: boolean }) {
  const mut = useSenhaMestra(empresaId)
  const [open, setOpen] = useState(false)
  const [atual, setAtual] = useState('')
  const [nova, setNova] = useState('')

  async function salvar() {
    if (nova.length < 4) { toast.error('A senha nova deve ter ao menos 4 caracteres.'); return }
    try { await mut.mutateAsync({ senha_atual: atual, senha_nova: nova }); toast.success('Senha mestra atualizada.'); setOpen(false); setAtual(''); setNova('') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao alterar senha.') }
  }

  return (
    <>
      <Button variant="outline" onClick={() => setOpen(true)}><KeyRound size={16} /> Senha mestra</Button>
      <FormDialog open={open} onOpenChange={setOpen} title={tem ? 'Alterar senha mestra' : 'Definir senha mestra'} loading={mut.isPending} onConfirm={salvar}>
        {tem && <Field label="Senha atual" required><Input type="password" value={atual} onChange={(e) => setAtual(e.target.value)} /></Field>}
        <Field label="Senha nova" required><Input type="password" value={nova} onChange={(e) => setNova(e.target.value)} /></Field>
      </FormDialog>
    </>
  )
}
