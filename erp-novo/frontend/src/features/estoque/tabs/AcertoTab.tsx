import { useState } from 'react'
import { SlidersHorizontal } from 'lucide-react'
import { Button, Card, CardContent, Field, AsyncSelect, Input, Select, SelectTrigger, SelectValue, SelectContent, SelectItem, toast } from '@/components/ui'
import { useAcerto } from '../api'

export function AcertoTab() {
  const acerto = useAcerto()
  const [setorId, setSetorId] = useState<number | null>(null); const [setorLabel, setSetorLabel] = useState<string | null>(null)
  const [produtoId, setProdutoId] = useState<number | null>(null); const [produtoLabel, setProdutoLabel] = useState<string | null>(null)
  const [mov, setMov] = useState('ENTRADA'); const [qtde, setQtde] = useState(''); const [obs, setObs] = useState('')

  async function salvar() {
    if (!setorId || !produtoId || !qtde || !obs) { toast.error('Preencha setor, produto, quantidade e descrição.'); return }
    try {
      await acerto.mutateAsync({ setor_id: setorId, produto_id: produtoId, movimentacao: mov, quantidade: Number(qtde), observacao: obs })
      toast.success('Acerto realizado.'); setQtde(''); setObs('')
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro no acerto.') }
  }

  return (
    <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Setor" required><AsyncSelect endpoint="/lookups/setores" value={setorId} valueLabel={setorLabel} onChange={(id, o) => { setSetorId(id); setSetorLabel(o?.label ?? null) }} /></Field>
      <Field label="Produto" required><AsyncSelect endpoint="/lookups/produtos" value={produtoId} valueLabel={produtoLabel} onChange={(id, o) => { setProdutoId(id); setProdutoLabel(o?.label ?? null) }} /></Field>
      <Field label="Movimentação" required>
        <Select value={mov} onValueChange={setMov}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent><SelectItem value="ENTRADA">Entrada (+)</SelectItem><SelectItem value="SAIDA">Saída (−)</SelectItem></SelectContent>
        </Select>
      </Field>
      <Field label="Quantidade" required><Input type="number" step="0.0001" value={qtde} onChange={(e) => setQtde(e.target.value)} /></Field>
      <Field label="Descrição / motivo" required className="md:col-span-2"><Input value={obs} onChange={(e) => setObs(e.target.value)} /></Field>
      <div><Button loading={acerto.isPending} onClick={salvar}><SlidersHorizontal size={16} /> Aplicar acerto</Button></div>
    </CardContent></Card>
  )
}
