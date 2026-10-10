import { useState } from 'react'
import { SlidersHorizontal } from 'lucide-react'
import { Button, Card, CardContent, Field, AsyncSelect, Input, Select, SelectTrigger, SelectValue, SelectContent, SelectItem, toast } from '@/components/ui'
import { useLancamentoManual } from '../api'

// Uma chave por lançamento: o mesmo envio repetido (rede caiu, duplo clique)
// chega com a mesma chave e o servidor não lança de novo. Só é trocada depois
// que o servidor confirma — trocar no erro faria o reenvio virar outro lançamento.
const novaChave = () => crypto.randomUUID()

export function AcertoTab() {
  const lancar = useLancamentoManual()
  const [chave, setChave] = useState(novaChave)
  const [setorId, setSetorId] = useState<number | null>(null); const [setorLabel, setSetorLabel] = useState<string | null>(null)
  const [produtoId, setProdutoId] = useState<number | null>(null); const [produtoLabel, setProdutoLabel] = useState<string | null>(null)
  const [mov, setMov] = useState<'ENTRADA' | 'SAIDA'>('ENTRADA'); const [qtde, setQtde] = useState(''); const [motivo, setMotivo] = useState('')

  function trocarMovimentacao(v: string) {
    setMov(v as 'ENTRADA' | 'SAIDA')
    // A lista de setores muda com a movimentação (entrada só em depósito/loja):
    // manter o setor escolhido deixaria selecionado um que a nova lista não tem.
    setSetorId(null); setSetorLabel(null)
  }

  async function salvar() {
    if (!setorId || !produtoId || !motivo.trim()) { toast.error('Preencha setor, produto, quantidade e motivo.'); return }
    if (!(Number(qtde) > 0)) { toast.error('Informe uma quantidade maior que zero.'); return }
    try {
      await lancar.mutateAsync({ chave, movimentacao: mov, setor_id: setorId, produto_id: produtoId, quantidade: Number(qtde), motivo: motivo.trim() })
      toast.success(mov === 'ENTRADA' ? 'Entrada lançada.' : 'Saída lançada.'); setQtde(''); setMotivo(''); setChave(novaChave())
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro no lançamento.') }
  }

  return (
    <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Movimentação" required>
        <Select value={mov} onValueChange={trocarMovimentacao}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent><SelectItem value="ENTRADA">Entrada (+)</SelectItem><SelectItem value="SAIDA">Saída (−)</SelectItem></SelectContent>
        </Select>
      </Field>
      {/* Entrada direta só existe em depósito e loja: o que está com uma pessoa ou num veículo chega por transferência. */}
      <Field label="Setor" required><AsyncSelect key={mov} endpoint="/lookups/setores" params={mov === 'ENTRADA' ? { armazens: 1 } : undefined} value={setorId} valueLabel={setorLabel} onChange={(id, o) => { setSetorId(id); setSetorLabel(o?.label ?? null) }} /></Field>
      <Field label="Produto" required><AsyncSelect endpoint="/lookups/produtos" value={produtoId} valueLabel={produtoLabel} onChange={(id, o) => { setProdutoId(id); setProdutoLabel(o?.label ?? null) }} /></Field>
      <Field label="Quantidade" required><Input type="number" min="0" step="0.001" value={qtde} onChange={(e) => setQtde(e.target.value)} /></Field>
      <Field label="Motivo" required className="md:col-span-2"><Input maxLength={255} value={motivo} onChange={(e) => setMotivo(e.target.value)} /></Field>
      <div><Button loading={lancar.isPending} onClick={salvar}><SlidersHorizontal size={16} /> Lançar</Button></div>
    </CardContent></Card>
  )
}
