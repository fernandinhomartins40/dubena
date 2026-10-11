import { Card, CardContent, Field, Input, CheckboxField } from '@/components/ui'
import type { ConfigSubtabProps } from './types'

export function ImpressaoTab({ form, campo }: ConfigSubtabProps) {
  return (
    <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Tipo de impressão"><Input value={form.impressao_tipo ?? ''} onChange={(e) => campo('impressao_tipo', e.target.value)} /></Field>
      <Field label="Modelo"><Input value={form.impressao_modelo ?? ''} onChange={(e) => campo('impressao_modelo', e.target.value)} /></Field>
      <Field label="Porta"><Input value={form.impressao_porta ?? ''} onChange={(e) => campo('impressao_porta', e.target.value)} /></Field>
      <Field label="Vias do pedido"><Input type="number" value={form.impressao_vias_pedido ?? ''} onChange={(e) => campo('impressao_vias_pedido', e.target.value)} /></Field>
      <div className="md:col-span-2"><CheckboxField label="Impressão automática" checked={!!form.impressao_automatica} onChange={(b) => campo('impressao_automatica', b)} /></div>
    </CardContent></Card>
  )
}
