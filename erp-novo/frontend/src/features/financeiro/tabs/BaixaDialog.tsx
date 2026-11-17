import { useState } from 'react'
import { ChevronDown } from 'lucide-react'
import { AsyncSelect, Field, FormDialog, Input, toast } from '@/components/ui'
import { usePreference } from '@/lib/usePreference'
import { brl, data as fmtData, decimalBR } from '@/lib/format'
import { useBaixarParcela, type Lancamento } from '../api'

/** Lê um valor digitado em pt-BR; vazio vale zero. `null` = texto que não é número. */
function lerValor(texto: string): number | null {
  if (texto.trim() === '') return 0
  // Os campos são em reais: o decimalBR aceita "5%" (serve a campos de
  // percentual) e o desconto de 5% virava R$ 5,00 (E2E da Rodada 3).
  if (texto.includes('%')) return null
  const n = decimalBR(texto)
  return Number.isFinite(n) ? Math.round(n * 100) / 100 : null
}

/**
 * Receber/pagar uma conta em três passos: botão na linha → conferir a conta de
 * destino (já vem a última usada) → confirmar. Juros, multa e desconto ficam
 * em "ajustar valor" — o caso comum é receber o valor cheio.
 *
 * Os campos são texto lido por `decimalBR`, e não `type=number`: o campo
 * numérico do navegador transformava "1.000,00" em 1 e aceitava "-10", e o
 * botão chegava a anunciar "Receber -R$ 879,00" (PIE R2-08, P2-D-16). A tela
 * recusa antes de enviar; o servidor confere tudo de novo.
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
  const [recebido, setRecebido] = useState('')
  const destino = conta ?? ultima

  const receber = parcela?.pagarreceber === 'R'
  const verbo = receber ? 'Receber' : 'Pagar'
  const j = lerValor(juros); const m = lerValor(multa); const d = lerValor(desconto); const r = lerValor(recebido)
  const total = Math.round(((parcela?.valor ?? 0) + (j ?? 0) + (m ?? 0) - (d ?? 0)) * 100) / 100
  const parcial = recebido.trim() !== '' && r !== null && r > 0 && r < total
  const agora = parcial ? (r as number) : total

  /** Mesmas regras do CaixaService::baixarParcela, com a mensagem antes do envio. */
  function problema(): string | null {
    for (const [nome, v] of [['Juros', j], ['Multa', m], ['Desconto', d], ['Valor recebido', r]] as const) {
      if (v === null) return `${nome}: valor inválido. Use números como 1.000,50.`
      if (v < 0) return `${nome} não pode ser negativo.`
    }
    if (total < 0) return 'Desconto maior que o valor da parcela com juros e multa.'
    if (recebido.trim() !== '') {
      if ((r as number) <= 0) return 'O valor recebido precisa ser maior que zero.'
      if ((r as number) > total) return `O valor ${receber ? 'recebido' : 'pago'} é maior que o total de ${brl(total)}.`
      if (parcial && (r as number) <= (j ?? 0) + (m ?? 0) - (d ?? 0)) return 'O valor não cobre os juros e a multa: aumente o valor ou retire os encargos.'
    }
    return null
  }

  function fechar() {
    setConta(null); setAjustar(false); setJuros(''); setMulta(''); setDesconto(''); setRecebido('')
    onClose()
  }

  async function confirmar() {
    if (!parcela || baixar.isPending) return
    const erro = problema()
    if (erro) { toast.error(erro); return }
    if (!destino) { toast.error(receber ? 'Escolha em qual conta o dinheiro entrou.' : 'Escolha de qual conta o dinheiro saiu.'); return }
    try {
      await baixar.mutateAsync({
        contaId: destino.id, parcela_id: parcela.id,
        juros: j ?? 0, multa: m ?? 0, desconto: d ?? 0,
        ...(parcial ? { valor_recebido: agora } : {}),
      })
      setUltima(destino)
      toast.success(`${receber ? 'Recebimento' : 'Pagamento'} de ${brl(agora)} registrado.`)
      fechar()
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? (e?.response ? 'Não foi possível registrar.' : 'Não foi possível registrar: sem conexão com o servidor. Nada foi gravado.'))
    }
  }

  // O rótulo nunca mostra valor negativo: com entrada inválida, só o verbo.
  const rotulo = problema() ? verbo : `${verbo} ${brl(agora)}`

  return (
    <FormDialog
      open={parcela !== null} onOpenChange={(o) => { if (!o) fechar() }}
      title={receber ? 'Registrar recebimento' : 'Registrar pagamento'}
      description={receber ? 'Confira a conta e o valor e confirme.' : 'Confira a conta e o valor e confirme.'}
      confirmLabel={rotulo}
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
      <Field label={receber ? 'Valor recebido agora' : 'Valor pago agora'} hint={`Em branco = o total (${brl(total < 0 ? 0 : total)}). Menos que o total deixa o restante em aberto, com o mesmo vencimento.`}>
        <Input inputMode="decimal" autoComplete="off" placeholder={brl(total < 0 ? 0 : total)} value={recebido} onChange={(e) => setRecebido(e.target.value)} />
      </Field>
      {parcial && <p role="status" className="text-sm font-medium">Fica em aberto: {brl(total - agora)}</p>}
      <button type="button" className="flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground" aria-expanded={ajustar} onClick={() => setAjustar((v) => !v)}>
        <ChevronDown size={15} className={ajustar ? '' : '-rotate-90'} /> Ajustar valor (juros, multa, desconto)
      </button>
      {ajustar && (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <Field label="Juros"><Input inputMode="decimal" autoComplete="off" placeholder="0,00" value={juros} onChange={(e) => setJuros(e.target.value)} /></Field>
          <Field label="Multa"><Input inputMode="decimal" autoComplete="off" placeholder="0,00" value={multa} onChange={(e) => setMulta(e.target.value)} /></Field>
          <Field label="Desconto"><Input inputMode="decimal" autoComplete="off" placeholder="0,00" value={desconto} onChange={(e) => setDesconto(e.target.value)} /></Field>
        </div>
      )}
    </FormDialog>
  )
}
