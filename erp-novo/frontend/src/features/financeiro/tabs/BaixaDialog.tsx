import { useState } from 'react'
import { ChevronDown } from 'lucide-react'
import { AsyncSelect, Field, FormDialog, Input, toast } from '@/components/ui'
import { usePreference } from '@/lib/usePreference'
import { brl, data as fmtData } from '@/lib/format'
import { useBaixarParcela, type Lancamento } from '../api'

/**
 * Receber/pagar uma conta em três passos: botão na linha → conferir a conta de
 * destino (já vem a última usada) → confirmar. Juros, multa e desconto ficam
 * em "ajustar valor" — o caso comum é receber o valor cheio.
 */
export function BaixaDialog({ parcela, onClose }: { parcela: Lancamento | null; onClose: () => void }) {
  const baixar = useBaixarParcela()
  // A última conta usada fica lembrada só neste navegador: é conveniência; o
  // servidor confere a conta a cada baixa.
  const [ultima, setUltima] = usePreference<{ id: number; label: string } | null>('erpnovo.financeiro.ultimaConta', null)
  const [conta, setConta] = useState<{ id: number; label: string } | null>(null)
  const [ajustar, setAjustar] = useState(false)
  const [juros, setJuros] = useState('')
  const [multa, setMulta] = useState('')
  const [desconto, setDesconto] = useState('')
  const destino = conta ?? ultima

  const receber = parcela?.pagarreceber === 'R'
  const total = (parcela?.valor ?? 0) + (Number(juros) || 0) + (Number(multa) || 0) - (Number(desconto) || 0)

  function fechar() {
    setConta(null); setAjustar(false); setJuros(''); setMulta(''); setDesconto('')
    onClose()
  }

  async function confirmar() {
    if (!parcela) return
    if (!destino) { toast.error(receber ? 'Escolha em qual conta o dinheiro entrou.' : 'Escolha de qual conta o dinheiro saiu.'); return }
    try {
      await baixar.mutateAsync({
        contaId: destino.id, parcela_id: parcela.id,
        juros: Number(juros) || 0, multa: Number(multa) || 0, desconto: Number(desconto) || 0,
      })
      setUltima(destino)
      toast.success(`${receber ? 'Recebimento' : 'Pagamento'} de ${brl(total)} registrado.`)
      fechar()
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Não foi possível registrar.') }
  }

  return (
    <FormDialog
      open={parcela !== null} onOpenChange={(o) => { if (!o) fechar() }}
      title={receber ? 'Registrar recebimento' : 'Registrar pagamento'}
      confirmLabel={`${receber ? 'Receber' : 'Pagar'} ${brl(total)}`}
      loading={baixar.isPending} onConfirm={confirmar}
    >
      {parcela && (
        <div className="rounded-lg border bg-secondary/40 p-3 text-sm">
          <p className="font-medium">{parcela.cliente ?? 'Sem cliente/fornecedor'}</p>
          <p className="text-muted-foreground">{parcela.descricao || parcela.documento || `Parcela ${parcela.numero}`} · vence {fmtData(parcela.datavencimento)}</p>
          <p className="mt-1 text-lg font-bold tabular-nums">{brl(parcela.valor)}</p>
        </div>
      )}
      <Field label={receber ? 'Entrou em qual conta?' : 'Saiu de qual conta?'} required>
        <AsyncSelect endpoint="/lookups/contas" value={destino?.id ?? null} valueLabel={destino?.label}
          onChange={(id, o) => setConta(id ? { id, label: o?.label ?? '' } : null)} />
      </Field>
      <button type="button" className="flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground" aria-expanded={ajustar} onClick={() => setAjustar((v) => !v)}>
        <ChevronDown size={15} className={ajustar ? '' : '-rotate-90'} /> Ajustar valor (juros, multa, desconto)
      </button>
      {ajustar && (
        <div className="grid grid-cols-3 gap-3">
          <Field label="Juros"><Input type="number" step="0.01" min={0} inputMode="decimal" value={juros} onChange={(e) => setJuros(e.target.value)} /></Field>
          <Field label="Multa"><Input type="number" step="0.01" min={0} inputMode="decimal" value={multa} onChange={(e) => setMulta(e.target.value)} /></Field>
          <Field label="Desconto"><Input type="number" step="0.01" min={0} inputMode="decimal" value={desconto} onChange={(e) => setDesconto(e.target.value)} /></Field>
        </div>
      )}
    </FormDialog>
  )
}
