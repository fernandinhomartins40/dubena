import { Navigation, Power } from 'lucide-react'
import {
  PageHeader, Badge, DataTable, type Column, EmptyState,
} from '@/components/ui'
import { useUltimasPosicoes, type UltimaPosicao } from './extraApi'

const fmtDt = (s: string | null) => (s ? new Date(s).toLocaleString('pt-BR') : '—')

export function MonitoraPage() {
  const { data, isLoading } = useUltimasPosicoes()

  const columns: Column<UltimaPosicao>[] = [
    { key: 'placa', header: 'Veículo', cell: (v) => <span className="font-medium">{v.placa || `#${v.veiculo_id}`}</span> },
    {
      key: 'pos', header: 'Posição', cell: (v) => (
        <a className="text-primary hover:underline tabular-nums" target="_blank" rel="noreferrer"
          href={`https://www.google.com/maps?q=${v.latitude},${v.longitude}`}>
          {v.latitude.toFixed(5)}, {v.longitude.toFixed(5)}
        </a>
      ),
    },
    { key: 'vel', header: 'Velocidade', cell: (v) => <span className="tabular-nums">{v.velocidade.toFixed(0)} km/h</span> },
    { key: 'ign', header: 'Ignição', cell: (v) => v.ignicao ? <Badge variant="success"><Power size={12} /> Ligada</Badge> : <Badge variant="secondary">Desligada</Badge> },
    { key: 'dt', header: 'Última atualização', cell: (v) => fmtDt(v.registrado_em) },
  ]

  return (
    <div>
      <PageHeader title="Monitora (GPS)" subtitle="Últimas posições da frota — atualiza a cada 30s" />
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.veiculo_id}
        empty={<EmptyState icon={<Navigation />} title="Sem posições" description="Nenhum veículo reportou posição (rastreamento é gate externo)." />} />
    </div>
  )
}
