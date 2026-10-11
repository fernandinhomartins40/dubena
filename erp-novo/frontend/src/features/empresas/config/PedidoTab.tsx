import { Card, CardContent, Field, Input, CheckboxField } from '@/components/ui'
import type { ConfigSubtabProps } from './types'

export function PedidoTab({ form, campo }: ConfigSubtabProps) {
  return (
    <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Tempo de entrega (min)"><Input type="number" value={form.tempoentrega ?? ''} onChange={(e) => campo('tempoentrega', e.target.value)} /></Field>
      <Field label="Tempo urgente (min)"><Input type="number" value={form.tempourgente ?? ''} onChange={(e) => campo('tempourgente', e.target.value)} /></Field>
      <Field label="Máximo de parcelas"><Input type="number" value={form.maximoparcelas ?? ''} onChange={(e) => campo('maximoparcelas', e.target.value)} /></Field>
      <Field label="Dias validação cartão"><Input type="number" value={form.pedido_valida_cartao_dias ?? ''} onChange={(e) => campo('pedido_valida_cartao_dias', e.target.value)} /></Field>
      <div className="md:col-span-2 grid grid-cols-2 sm:grid-cols-3 gap-2 border-t border-border pt-3">
        <CheckboxField label="Valida atraso" checked={!!form.valida_atraso} onChange={(b) => campo('valida_atraso', b)} />
        <CheckboxField label="Valida cartão" checked={!!form.pedido_valida_cartao} onChange={(b) => campo('pedido_valida_cartao', b)} />
        <CheckboxField label="Valida Gás Bolso" checked={!!form.valida_gas_bolso} onChange={(b) => campo('valida_gas_bolso', b)} />
        <CheckboxField label="Valida coordenadas" checked={!!form.valida_coordenadas_entrega} onChange={(b) => campo('valida_coordenadas_entrega', b)} />
        <CheckboxField label="Valida PIX entrega" checked={!!form.valida_pix_entrega} onChange={(b) => campo('valida_pix_entrega', b)} />
        <CheckboxField label="Emite NFC-e no pedido" checked={!!form.pedido_emite_nfce} onChange={(b) => campo('pedido_emite_nfce', b)} />
      </div>
    </CardContent></Card>
  )
}
