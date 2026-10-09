import { useState } from 'react'
import { ArrowRightLeft } from 'lucide-react'
import {
  Button, DataTable, type Column, EmptyState, Field, AsyncSelect,
  FormDialog, toast,
} from '@/components/ui'
import { useTransferencias, useCriarTransferencia } from '../api'
import { dataHora as fmtData, num } from '@/lib/format'
import { ItensEditor } from './ItensEditor'

// Uma chave por ABERTURA do formulário: o mesmo envio repetido (rede caiu,
// duplo clique) chega com a mesma chave e o servidor não duplica o movimento.
const novaChave = () => crypto.randomUUID()

export function TransferenciaTab() {
  const { data, isLoading, error, refetch } = useTransferencias()
  const criar = useCriarTransferencia()
  const [open, setOpen] = useState(false)
  const [chave, setChave] = useState(novaChave)
  const [origem, setOrigem] = useState<number | null>(null); const [origemL, setOrigemL] = useState<string | null>(null)
  const [destino, setDestino] = useState<number | null>(null); const [destinoL, setDestinoL] = useState<string | null>(null)
  const [itens, setItens] = useState<any[]>([])

  function limpar() {
    setOrigem(null); setOrigemL(null); setDestino(null); setDestinoL(null); setItens([]); setChave(novaChave())
  }

  async function salvar() {
    if (!origem || !destino || itens.length === 0) { toast.error('Informe origem, destino e ao menos um item.'); return }
    if (origem === destino) { toast.error('Origem e destino devem ser setores diferentes.'); return }
    try {
      await criar.mutateAsync({
        chave,
        setor_origem_id: origem,
        setor_destino_id: destino,
        itens: itens.map((i) => ({ produto_id: i.produto_id, quantidade: Number(i.quantidade) })),
      })
      toast.success('Transferência realizada.'); setOpen(false); limpar()
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro na transferência.') }
  }

  // Cada transferência gera dois movimentos (saída na origem, entrada no
  // destino); a lista mostra os dois, com o sinal indicando o sentido.
  const columns: Column<any>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'data', header: 'Data', cell: (r) => fmtData(r.created_at) },
    { key: 'setor', header: 'Setor', cell: (r) => r.setor?.descricao ?? `#${r.setor_id}` },
    { key: 'produto', header: 'Produto', cell: (r) => r.produto?.descricao ?? `#${r.produto_id}` },
    {
      key: 'quantidade', header: 'Quantidade', className: 'text-right',
      cell: (r) => <span className={Number(r.quantidade) < 0 ? 'text-destructive' : 'text-emerald-600'}>{num(r.quantidade, 0, 3)}</span>,
    },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end">
        <Button onClick={() => { limpar(); setOpen(true) }}><ArrowRightLeft size={16} /> Nova transferência</Button>
        <FormDialog open={open} onOpenChange={(next) => { setOpen(next); if (!next) limpar() }} title="Nova transferência entre setores"
          dirty={origem !== null || destino !== null || itens.length > 0} widthClass="max-w-3xl" loading={criar.isPending} onConfirm={salvar} confirmLabel="Transferir">
            <div className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field label="Setor de origem" required><AsyncSelect endpoint="/lookups/setores" value={origem} valueLabel={origemL} onChange={(id, o) => { setOrigem(id); setOrigemL(o?.label ?? null) }} /></Field>
                <Field label="Setor de destino" required><AsyncSelect endpoint="/lookups/setores" value={destino} valueLabel={destinoL} onChange={(id, o) => { setDestino(id); setDestinoL(o?.label ?? null) }} /></Field>
              </div>
              <ItensEditor itens={itens} setItens={setItens} />
            </div>
        </FormDialog>
      </div>
      <DataTable columns={columns} rows={data} loading={isLoading} error={error} onRetry={() => { void refetch() }} rowKey={(r) => r.id} empty={<EmptyState icon={<ArrowRightLeft />} title="Nenhuma transferência" />} />
    </>
  )
}
