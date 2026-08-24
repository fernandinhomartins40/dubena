import { useEffect, useState } from 'react'
import { FormDialog, Field, Input, Textarea, toast } from '@/components/ui'
import { useDevolverComodato, type Comodato } from './api'

/**
 * Devolução de vasilhame — parcial ou total.
 *
 * Substitui o `prompt()` do navegador, que não mostrava o saldo, não aceitava
 * data nem observação, e não deixava claro que devolver menos que o total era
 * possível. O operador acabava digitando o total só para "fechar" o comodato.
 *
 * Os botões de atalho existem porque a decisão real no balcão é binária —
 * "devolveu tudo" ou "devolveu alguns" — e digitar o número do total à mão é
 * onde entra o erro que depois precisa de estorno.
 */
export function DevolucaoDialog({ comodato, onOpenChange }: {
  comodato: Comodato | null
  onOpenChange: (v: boolean) => void
}) {
  const devolver = useDevolverComodato()
  const [quantidade, setQuantidade] = useState('')
  const [data, setData] = useState('')
  const [observacao, setObservacao] = useState('')

  const emPosse = comodato
    ? Number(comodato.quantidade) - Number(comodato.quantidade_devolvida)
    : 0

  useEffect(() => {
    if (comodato) {
      setQuantidade('')
      setData(new Date().toISOString().slice(0, 10))
      setObservacao('')
    }
  }, [comodato])

  const qtd = Number(quantidade)
  const invalido = !quantidade || Number.isNaN(qtd) || qtd <= 0 || qtd > emPosse + 0.0001
  const restante = Math.round((emPosse - (Number.isNaN(qtd) ? 0 : qtd)) * 1000) / 1000

  async function confirmar() {
    if (!comodato || invalido) return
    try {
      await devolver.mutateAsync({
        id: comodato.id,
        quantidade: qtd,
        data: data || undefined,
        observacao: observacao || undefined,
      })
      toast.success(
        restante > 0
          ? `Devolução registrada. Restam ${restante} com o cliente — contrato reemitido.`
          : 'Devolução integral registrada. Comodato encerrado.',
      )
      onOpenChange(false)
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao registrar a devolução.')
    }
  }

  return (
    <FormDialog
      open={comodato !== null}
      onOpenChange={onOpenChange}
      title="Registrar devolução"
      description={comodato
        ? `${comodato.cliente?.nome ?? 'Cliente'} — ${emPosse} ${comodato.produto?.descricao ?? 'vasilhame'} em poder dele`
        : undefined}
      confirmLabel="Registrar devolução"
      loading={devolver.isPending}
      confirmDisabled={invalido}
      onConfirm={confirmar}
    >
      <Field label="Quantidade devolvida" required>
        <div className="flex gap-2">
          <Input
            type="number" min={0} step="0.001" max={emPosse} autoFocus
            value={quantidade} onChange={(e) => setQuantidade(e.target.value)}
            placeholder={`até ${emPosse}`}
          />
          <button
            type="button"
            className="shrink-0 rounded-md border border-input px-3 text-sm hover:bg-accent"
            onClick={() => setQuantidade(String(emPosse))}
          >
            Tudo ({emPosse})
          </button>
        </div>
      </Field>

      {!invalido && (
        <p className={`text-sm ${restante > 0 ? 'text-amber-600' : 'text-emerald-600'}`}>
          {restante > 0
            ? `Restam ${restante} com o cliente. Uma nova versão do contrato será emitida com o saldo já descontado.`
            : 'Devolução integral: o comodato será encerrado.'}
        </p>
      )}

      <Field label="Data da devolução">
        <Input type="date" value={data} onChange={(e) => setData(e.target.value)} />
      </Field>

      <Field label="Observação">
        <Textarea
          rows={2} value={observacao} onChange={(e) => setObservacao(e.target.value)}
          placeholder="Ex.: recebido no balcão, um vasilhame com avaria"
        />
      </Field>
    </FormDialog>
  )
}
