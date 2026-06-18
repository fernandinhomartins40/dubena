import { useState } from 'react'
import { Trash2, Plus, MessageCircle } from 'lucide-react'
import { Button, Input } from '@/components/ui'
import { AsyncSelect } from '@/components/AsyncSelect'
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
  const [erro, setErro] = useState<string | null>(null)

  async function adicionar() {
    setErro(null)
    if (!telefone || !tipoId) { setErro('Informe o telefone e o tipo.'); return }
    await add.mutateAsync({ telefone, whatsapp, telefonetipo_id: tipoId })
    setTelefone(''); setWhatsapp(false); setTipoId(null); setTipoLabel(null)
  }

  return (
    <div className="col-span-full space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <Input label="Telefone" value={telefone} onChange={(e) => setTelefone(e.target.value)} />
        <AsyncSelect
          label="Tipo" endpoint="/lookups/telefone-tipos"
          value={tipoId} valueLabel={tipoLabel}
          onChange={(id, opt) => { setTipoId(id); setTipoLabel(opt?.label ?? null) }}
        />
        <label className="flex items-center gap-2 text-sm py-2">
          <input type="checkbox" checked={whatsapp} onChange={(e) => setWhatsapp(e.target.checked)} />
          WhatsApp
        </label>
        <Button type="button" onClick={adicionar} disabled={add.isPending}><Plus size={16} /> Adicionar</Button>
      </div>
      {erro && <p className="text-sm text-red-600">{erro}</p>}

      <div className="border border-slate-200 dark:border-slate-800 rounded-lg divide-y divide-slate-100 dark:divide-slate-800">
        {isLoading ? (
          <p className="px-4 py-3 text-sm text-slate-400">Carregando…</p>
        ) : telefones && telefones.length > 0 ? (
          telefones.map((t) => (
            <div key={t.id} className="flex items-center justify-between px-4 py-2.5">
              <div className="flex items-center gap-2">
                <span className="font-medium">{t.telefone}</span>
                {t.whatsapp === 1 && <MessageCircle size={15} className="text-green-600" />}
              </div>
              <button onClick={() => del.mutate(t.id)} className="text-slate-400 hover:text-red-600" title="Remover">
                <Trash2 size={16} />
              </button>
            </div>
          ))
        ) : (
          <p className="px-4 py-3 text-sm text-slate-400">Nenhum telefone cadastrado.</p>
        )}
      </div>
    </div>
  )
}
