import { Card, CardContent, Field, Input, CheckboxField, AsyncSelect } from '@/components/ui'
import type { ConfigSubtabProps } from './types'

export function EstoqueTab({ form, campo, labels, lbl }: ConfigSubtabProps) {
  return (
    <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Setor principal"><AsyncSelect endpoint="/lookups/setores" value={form.setor_principal_id ?? null} valueLabel={labels.setor}
        onChange={(id, o) => { campo('setor_principal_id', id); lbl('setor', o?.label ?? null) }} /></Field>
      <Field label="Dias p/ inativar por falta de compra"><Input type="number" value={form.dias_inativo_compra ?? ''} onChange={(e) => campo('dias_inativo_compra', e.target.value)} /></Field>
      <div className="md:col-span-2"><CheckboxField label="Permite estoque negativo" checked={!!form.permite_estoque_negativo} onChange={(b) => campo('permite_estoque_negativo', b)} /></div>
    </CardContent></Card>
  )
}
