import { Card, CardContent, Field, Input, CheckboxField } from '@/components/ui'
import type { ConfigSubtabProps } from './types'

export function PedidoTab({ form, campo }: ConfigSubtabProps) {
  return (
    <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Tempo de entrega (min)"><Input type="number" value={form.tempoentrega ?? ''} onChange={(e) => campo('tempoentrega', e.target.value)} /></Field>
      <Field label="Tempo urgente (min)"><Input type="number" value={form.tempourgente ?? ''} onChange={(e) => campo('tempourgente', e.target.value)} /></Field>
      <Field label="Máximo de parcelas"><Input type="number" value={form.maximoparcelas ?? ''} onChange={(e) => campo('maximoparcelas', e.target.value)} /></Field>
      <Field label="Dias validação cartão"><Input type="number" value={form.pedidovalidacartaodias ?? ''} onChange={(e) => campo('pedidovalidacartaodias', e.target.value)} /></Field>
      <div className="md:col-span-2 grid grid-cols-2 sm:grid-cols-3 gap-2 border-t border-border pt-3">
        <CheckboxField label="Valida atraso" checked={!!form.validaatraso} onChange={(b) => campo('validaatraso', b)} />
        <CheckboxField label="Valida cartão" checked={!!form.pedidovalidacartao} onChange={(b) => campo('pedidovalidacartao', b)} />
        <CheckboxField label="Valida Gás Bolso" checked={!!form.validagasbolso} onChange={(b) => campo('validagasbolso', b)} />
        <CheckboxField label="Valida coordenadas" checked={!!form.validacordenadasentrega} onChange={(b) => campo('validacordenadasentrega', b)} />
        <CheckboxField label="Valida PIX entrega" checked={!!form.validapixentrega} onChange={(b) => campo('validapixentrega', b)} />
        <CheckboxField label="Emite NFC-e no pedido" checked={!!form.pedidoemitenfce} onChange={(b) => campo('pedidoemitenfce', b)} />
      </div>
    </CardContent></Card>
  )
}
