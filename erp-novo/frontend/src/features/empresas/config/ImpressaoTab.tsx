import { Card, CardContent, Field, Input, CheckboxField } from '@/components/ui'
import type { ConfigSubtabProps } from './types'

export function ImpressaoTab({ form, campo }: ConfigSubtabProps) {
  return (
    <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Tipo de impressão"><Input value={form.impressaotipo ?? ''} onChange={(e) => campo('impressaotipo', e.target.value)} /></Field>
      <Field label="Modelo"><Input value={form.impressaomodelo ?? ''} onChange={(e) => campo('impressaomodelo', e.target.value)} /></Field>
      <Field label="Porta"><Input value={form.impressaoporta ?? ''} onChange={(e) => campo('impressaoporta', e.target.value)} /></Field>
      <Field label="Vias do pedido"><Input type="number" value={form.impressaoqtdviaspedido ?? ''} onChange={(e) => campo('impressaoqtdviaspedido', e.target.value)} /></Field>
      <div className="md:col-span-2"><CheckboxField label="Impressão automática" checked={!!form.impressaoautomatica} onChange={(b) => campo('impressaoautomatica', b)} /></div>
    </CardContent></Card>
  )
}
