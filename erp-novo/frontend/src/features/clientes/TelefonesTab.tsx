import { useState } from 'react'
import { Trash2, Plus, MessageCircle, Phone } from 'lucide-react'
import { Button, Field, Input, CheckboxField, AsyncSelect, AsyncState, Badge, toast } from '@/components/ui'
import { useTelefones, useAddTelefone, useDelTelefone } from './api'

/** Aba Telefones da ficha do cliente (sub-recurso na mesma página). */
export function TelefonesTab({ clienteId }: { clienteId: number }) {
  const { data: telefones, isLoading } = useTelefones(clienteId)
  const add = useAddTelefone(clienteId)
  const del = useDelTelefone(clienteId)

  const [telefone, setTelefone] = useState('')
  const [tipoId, setTipoId] = useState<number | null>(null)
  const [tipoLabel, setTipoLabel] = useState<string | null>(null)
  const [whatsapp, setWhatsapp] = useState(false)

  async function adicionar() {
    if (!telefone || !tipoId) { toast.error('Informe o telefone e o tipo.'); return }
    await add.mutateAsync({ telefone, whatsapp, telefonetipo_id: tipoId })
    setTelefone(''); setWhatsapp(false); setTipoId(null); setTipoLabel(null)
    toast.success('Telefone adicionado.')
  }

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end rounded-lg border border-border p-4">
        <Field label="Telefone"><Input value={telefone} onChange={(e) => setTelefone(e.target.value)} /></Field>
        <Field label="Tipo">
          <AsyncSelect endpoint="/lookups/telefone-tipos" value={tipoId} valueLabel={tipoLabel}
            onChange={(id, opt) => { setTipoId(id); setTipoLabel(opt?.label ?? null) }} />
        </Field>
        <CheckboxField label="WhatsApp" checked={whatsapp} onChange={setWhatsapp} />
        <Button onClick={adicionar} loading={add.isPending}><Plus size={16} /> Adicionar</Button>
      </div>

      <AsyncState loading={isLoading} empty={!telefones?.length}
        emptyIcon={<Phone />} emptyTitle="Nenhum telefone" emptyDescription="Adicione um telefone de contato.">
        <div className="rounded-lg border border-border divide-y divide-border">
          {telefones?.map((t) => (
            <div key={t.id} className="flex items-center justify-between px-4 py-3">
              <div className="flex items-center gap-2">
                <Phone size={15} className="text-muted-foreground" />
                <span className="font-medium">{t.telefone}</span>
                {t.whatsapp === 1 && <Badge variant="success"><MessageCircle size={12} className="mr-1" /> WhatsApp</Badge>}
              </div>
              <Button variant="ghost" size="icon" onClick={() => { del.mutate(t.id); toast.success('Telefone removido.') }} aria-label="Remover"><Trash2 size={16} /></Button>
            </div>
          ))}
        </div>
      </AsyncState>
    </div>
  )
}
