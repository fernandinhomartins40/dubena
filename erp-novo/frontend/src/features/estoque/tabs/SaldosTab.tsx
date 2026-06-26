import { useState } from 'react'
import { Warehouse } from 'lucide-react'
import { DataTable, type Column, EmptyState, AsyncSelect, SearchBar } from '@/components/ui'
import { useSaldos, type SaldoRow } from '../api'
import { qtd as fmt } from '@/lib/format'
import { useBusca } from '@/lib/useBusca'

export function SaldosTab() {
  const [setorId, setSetorId] = useState<number | null>(null)
  const [setorLabel, setSetorLabel] = useState<string | null>(null)
  const { busca, setBusca, q, submit } = useBusca()
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
      <SearchBar value={busca} onChange={setBusca} onSearch={submit} placeholder="Buscar produto…">
        <div className="w-56"><AsyncSelect endpoint="/lookups/setores" value={setorId} valueLabel={setorLabel} placeholder="Filtrar setor"
          onChange={(id, o) => { setSetorId(id); setSetorLabel(o?.label ?? null) }} /></div>
      </SearchBar>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id}
        empty={<EmptyState icon={<Warehouse />} title="Sem saldo" description="Nenhum saldo encontrado para o filtro." />} />
    </>
  )
}
