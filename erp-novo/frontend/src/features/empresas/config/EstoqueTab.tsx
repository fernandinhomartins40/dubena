import { Card, CardContent, Field, Input, CheckboxField, AsyncSelect } from '@/components/ui'
import type { ConfigSubtabProps } from './types'

export function EstoqueTab({ form, campo, labels, lbl }: ConfigSubtabProps) {
  return (
    <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Setor principal"><AsyncSelect endpoint="/lookups/setores" value={form.setorprincipal_id ?? null} valueLabel={labels.setor}
        onChange={(id, o) => { campo('setorprincipal_id', id); lbl('setor', o?.label ?? null) }} /></Field>
      <Field label="Dias p/ inativar por falta de compra"><Input type="number" value={form.qnddiasinativocompra ?? ''} onChange={(e) => campo('qnddiasinativocompra', e.target.value)} /></Field>
      <div className="md:col-span-2"><CheckboxField label="Permite estoque negativo" checked={!!form.permiteestoquenegativo} onChange={(b) => campo('permiteestoquenegativo', b)} /></div>
    </CardContent></Card>
  )
}
