import { Card, CardContent, Field, AsyncSelect } from '@/components/ui'
import type { ConfigSubtabProps } from './types'

export function ContabilTab({ form, campo, labels, lbl }: ConfigSubtabProps) {
  return (
    <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Plano de contas principal"><AsyncSelect endpoint="/lookups/planos-conta" value={form.planoconta_id ?? null} valueLabel={labels.pc}
        onChange={(id, o) => { campo('planoconta_id', id); lbl('pc', o?.label ?? null) }} /></Field>
      <Field label="Centro de custo principal"><AsyncSelect endpoint="/lookups/centros-custo" value={form.centrocusto_id ?? null} valueLabel={labels.cc}
        onChange={(id, o) => { campo('centrocusto_id', id); lbl('cc', o?.label ?? null) }} /></Field>
      <Field label="PC Cartão"><AsyncSelect endpoint="/lookups/planos-conta" value={form.pccartao_id ?? null} valueLabel={labels.pccartao}
        onChange={(id, o) => { campo('pccartao_id', id); lbl('pccartao', o?.label ?? null) }} /></Field>
      <Field label="PC Vale-Gás"><AsyncSelect endpoint="/lookups/planos-conta" value={form.pcvalegas_id ?? null} valueLabel={labels.pcvg}
        onChange={(id, o) => { campo('pcvalegas_id', id); lbl('pcvg', o?.label ?? null) }} /></Field>
      <Field label="CC Vale-Gás"><AsyncSelect endpoint="/lookups/centros-custo" value={form.ccvalegas_id ?? null} valueLabel={labels.ccvg}
        onChange={(id, o) => { campo('ccvalegas_id', id); lbl('ccvg', o?.label ?? null) }} /></Field>
      <Field label="Conta malote"><AsyncSelect endpoint="/lookups/contas" value={form.maloteconta_id ?? null} valueLabel={labels.malote}
        onChange={(id, o) => { campo('maloteconta_id', id); lbl('malote', o?.label ?? null) }} /></Field>
    </CardContent></Card>
  )
}
