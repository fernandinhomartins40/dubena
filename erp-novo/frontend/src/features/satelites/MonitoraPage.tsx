import { Navigation, Power, MapPin, Map } from 'lucide-react'
import {
  Badge, type Column, ResourceList, PageHeader,
  Tabs, TabsList, TabsTrigger, TabsContent,
} from '@/components/ui'
import { dataHora } from '@/lib/format'
import { useUltimasPosicoes, type UltimaPosicao } from './extraApi'
import { CercasTab } from './CercasTab'
import { MapaAoVivoTab } from './MapaAoVivoTab'

export function MonitoraPage() {
  return (
    <div>
      <PageHeader title="Monitora (GPS)" subtitle="Frota no mapa, posições e cercas (geofencing)" />
      <Tabs defaultValue="mapa">
        <TabsList>
          <TabsTrigger value="mapa"><Map size={15} className="mr-1" /> Mapa ao vivo</TabsTrigger>
          <TabsTrigger value="posicoes"><Navigation size={15} className="mr-1" /> Posições</TabsTrigger>
          <TabsTrigger value="cercas"><MapPin size={15} className="mr-1" /> Cercas</TabsTrigger>
        </TabsList>
        <TabsContent value="mapa"><MapaAoVivoTab /></TabsContent>
        <TabsContent value="posicoes"><PosicoesTab /></TabsContent>
        <TabsContent value="cercas"><CercasTab /></TabsContent>
      </Tabs>
    </div>
  )
}

function PosicoesTab() {
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
    { key: 'vel', header: 'Velocidade', align: 'right', cell: (v) => <span className="tabular-nums">{v.velocidade.toFixed(0)} km/h</span> },
    { key: 'ign', header: 'Ignição', cell: (v) => v.ignicao ? <Badge variant="success"><Power size={12} /> Ligada</Badge> : <Badge variant="secondary">Desligada</Badge> },
    { key: 'dt', header: 'Última atualização', cell: (v) => dataHora(v.registrado_em) },
  ]

  return (
    <ResourceList
      title="Últimas posições"
      subtitle="Frota — atualiza a cada 30s"
      columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.veiculo_id}
      emptyIcon={<Navigation />} emptyTitle="Sem posições"
      emptyDescription="Nenhum veículo reportou posição (rastreamento é gate externo)."
    />
  )
}
