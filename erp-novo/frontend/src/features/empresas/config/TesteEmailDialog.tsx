import { useState } from 'react'
import { Mail } from 'lucide-react'
import { Button, Field, Input, FormDialog, toast } from '@/components/ui'
import { useTestarEmail } from '../api'

export function TesteEmailDialog({ empresaId }: { empresaId: number }) {
  const mut = useTestarEmail(empresaId)
  const [open, setOpen] = useState(false)
  const [to, setTo] = useState('')

  async function enviar() {
    if (!to) { toast.error('Informe o destinatário.'); return }
    try { await mut.mutateAsync({ to }); toast.success('E-mail de teste enviado.'); setOpen(false); setTo('') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Falha ao enviar.') }
  }

  return (
    <>
      <Button variant="outline" onClick={() => setOpen(true)}><Mail size={16} /> Testar e-mail</Button>
      <FormDialog open={open} onOpenChange={setOpen} title="Testar configuração de e-mail" confirmLabel="Enviar teste" loading={mut.isPending} onConfirm={enviar}>
        <p className="text-sm text-muted-foreground">Salve as configurações de e-mail antes de testar. Enviaremos um e-mail de teste ao destinatário.</p>
        <Field label="Enviar para" required><Input type="email" value={to} onChange={(e) => setTo(e.target.value)} /></Field>
      </FormDialog>
    </>
  )
}
