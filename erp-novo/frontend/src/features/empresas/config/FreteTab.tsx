import { Card, CardContent, Field, Input, AsyncSelect } from '@/components/ui'
import type { ConfigSubtabProps } from './types'

export function FreteTab({ form, campo, labels, lbl }: ConfigSubtabProps) {
  return (
    <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="PC Frete"><AsyncSelect endpoint="/lookups/planos-conta" value={form.pcfrete_id ?? null} valueLabel={labels.pcf}
        onChange={(id, o) => { campo('pcfrete_id', id); lbl('pcf', o?.label ?? null) }} /></Field>
      <Field label="CC Frete"><AsyncSelect endpoint="/lookups/centros-custo" value={form.ccfrete_id ?? null} valueLabel={labels.ccf}
        onChange={(id, o) => { campo('ccfrete_id', id); lbl('ccf', o?.label ?? null) }} /></Field>
      <Field label="Valor frete GP"><Input value={form.valorfretegp ?? ''} onChange={(e) => campo('valorfretegp', e.target.value)} /></Field>
    </CardContent></Card>
  )
}
