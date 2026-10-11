import { Card, CardContent, Field, Input } from '@/components/ui'
import type { ConfigSubtabProps } from './types'

export function PercentuaisTab({ form, campo }: ConfigSubtabProps) {
  return (
    <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Encargos (%)"><Input value={form.percentual_encargos ?? ''} onChange={(e) => campo('percentual_encargos', e.target.value)} /></Field>
      <Field label="Provisão devedores (%)"><Input value={form.percentual_provisao_devedores ?? ''} onChange={(e) => campo('percentual_provisao_devedores', e.target.value)} /></Field>
      <Field label="Remuneração capital (%)"><Input value={form.percentual_remuneracao_capital ?? ''} onChange={(e) => campo('percentual_remuneracao_capital', e.target.value)} /></Field>
      <Field label="Distribuição resultado (%)"><Input value={form.percentual_distribuicao_resultado ?? ''} onChange={(e) => campo('percentual_distribuicao_resultado', e.target.value)} /></Field>
    </CardContent></Card>
  )
}
