import { Tag } from 'lucide-react'
import { DataTable, type Column, EmptyState } from '@/components/ui'
import { usePrecos } from './api'

interface PrecoEspecial { id: number; produto: string | null; produto_id: number; preco: number | null }

export function PrecosTab({ clienteId }: { clienteId: number }) {
  const { data, isLoading } = usePrecos(clienteId)
  const columns: Column<PrecoEspecial>[] = [
    { key: 'produto', header: 'Produto', cell: (p) => p.produto ?? `#${p.produto_id}` },
    { key: 'preco', header: 'Preço', align: 'right', cell: (p) => <span className="tabular-nums">{Number(p.preco ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</span> },
  ]
  return (
    <div className="space-y-3">
      <p className="text-sm text-muted-foreground">Preços especiais por produto deste cliente.</p>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(p) => p.id}
        empty={<EmptyState icon={<Tag />} title="Nenhum preço especial" description="Este cliente usa a tabela de preços padrão." />} />
    </div>
  )
}
