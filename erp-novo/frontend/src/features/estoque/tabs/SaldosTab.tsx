import { useState } from 'react'
import { Search, Warehouse } from 'lucide-react'
import { Button, Card, Input, DataTable, type Column, EmptyState, AsyncSelect } from '@/components/ui'
import { useSaldos, type SaldoRow } from '../api'
import { qtd as fmt } from '@/lib/format'

export function SaldosTab() {
  const [setorId, setSetorId] = useState<number | null>(null)
  const [setorLabel, setSetorLabel] = useState<string | null>(null)
  const [busca, setBusca] = useState(''); const [q, setQ] = useState('')
  const { data, isLoading } = useSaldos(setorId, q)

  const columns: Column<SaldoRow>[] = [
    { key: 'produto', header: 'Produto', cell: (r) => <span className="font-medium">{r.produto}</span> },
    { key: 'setor', header: 'Setor', cell: (r) => <span className="text-muted-foreground">{r.setor}</span> },
    { key: 'qtd', header: 'Quantidade', align: 'right', cell: (r) => <span className={`tabular-nums font-medium ${r.quantidade < 0 ? 'text-destructive' : ''}`}>{fmt(r.quantidade)}</span> },
    { key: 'min', header: 'Mín.', align: 'right', cell: (r) => <span className="tabular-nums text-muted-foreground">{fmt(r.quantidademinima)}</span> },
    { key: 'max', header: 'Máx.', align: 'right', cell: (r) => <span className="tabular-nums text-muted-foreground">{fmt(r.quantidademaxima)}</span> },
  ]
  return (
    <>
      <Card className="mb-4 p-3"><div className="flex flex-wrap gap-2 items-center">
        <div className="w-56"><AsyncSelect endpoint="/lookups/setores" value={setorId} valueLabel={setorLabel} placeholder="Filtrar setor"
          onChange={(id, o) => { setSetorId(id); setSetorLabel(o?.label ?? null) }} /></div>
        <div className="relative flex-1 min-w-[200px]">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar produto…" className="pl-9"
            onKeyDown={(e) => e.key === 'Enter' && setQ(busca)} />
        </div>
        <Button variant="secondary" onClick={() => setQ(busca)}>Buscar</Button>
      </div></Card>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id}
        empty={<EmptyState icon={<Warehouse />} title="Sem saldo" description="Nenhum saldo encontrado para o filtro." />} />
    </>
  )
}
