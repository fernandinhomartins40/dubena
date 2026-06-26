import { Card, CardContent, Field, Input } from '@/components/ui'
import type { ConfigSubtabProps } from './types'

export function PercentuaisTab({ form, campo }: ConfigSubtabProps) {
  return (
    <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Encargos (%)"><Input value={form.percentualencargos ?? ''} onChange={(e) => campo('percentualencargos', e.target.value)} /></Field>
      <Field label="Provisão devedores (%)"><Input value={form.percentualprovisaodevedores ?? ''} onChange={(e) => campo('percentualprovisaodevedores', e.target.value)} /></Field>
      <Field label="Remuneração capital (%)"><Input value={form.percentualremuneracaocapital ?? ''} onChange={(e) => campo('percentualremuneracaocapital', e.target.value)} /></Field>
      <Field label="Distribuição resultado (%)"><Input value={form.percentualdistribuicaoresul ?? ''} onChange={(e) => campo('percentualdistribuicaoresul', e.target.value)} /></Field>
    </CardContent></Card>
  )
}
