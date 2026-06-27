import { useEffect, useRef, useState } from 'react'
import { Route as RouteIcon, Download, OctagonPause, Gauge } from 'lucide-react'
import {
  Button, Card, CardContent, Field, Input, EmptyState, AsyncState, StatCard,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem, toast,
} from '@/components/ui'
import { carregarGoogleMaps } from '@/lib/googleMaps'
import { useVeiculos, useHistorico, useEventos, baixarEventos, useGoogleMapsKey } from './extraApi'

const CENTRO_PADRAO = { lat: -25.3935, lng: -51.4562 }
const hoje = () => new Date().toISOString().slice(0, 10)

/** Aba Rota (F2) — replay do trajeto + paradas/excessos por veículo num período. */
export function RotaTab() {
  const { data: key, isLoading: carregandoKey } = useGoogleMapsKey()
  const { data: veiculos } = useVeiculos()

  const [veiculoId, setVeiculoId] = useState<number | null>(null)
  const [de, setDe] = useState(hoje())
  const [ate, setAte] = useState(hoje())
  const [baixando, setBaixando] = useState(false)

  const { data: historico, isLoading: carregandoHist } = useHistorico(veiculoId, de, ate)
  const { data: eventos } = useEventos(veiculoId, de, ate)

  const mapRef = useRef<HTMLDivElement>(null)
  const gmap = useRef<any>(null)
  const overlays = useRef<any[]>([])
  const [pronto, setPronto] = useState(false)
  const [erroMapa, setErroMapa] = useState<string | null>(null)

  useEffect(() => {
    if (!key || !mapRef.current || gmap.current) return
    let vivo = true
    carregarGoogleMaps(key)
      .then((google) => {
        if (!vivo || !mapRef.current) return
        gmap.current = new google.maps.Map(mapRef.current, { center: CENTRO_PADRAO, zoom: 12, mapTypeControl: false, streetViewControl: false })
        setPronto(true)
      })
      .catch((e) => setErroMapa(e?.message ?? 'Não foi possível carregar o Google Maps.'))
    return () => { vivo = false }
  }, [key])

  // Redesenha trajeto + marcadores de parada/excesso.
  useEffect(() => {
    if (!pronto || !gmap.current) return
    const google = (window as any).google
    overlays.current.forEach((o) => o.setMap(null))
    overlays.current = []
    if (!historico?.length) return

    const path = historico.map((p) => ({ lat: p.latitude, lng: p.longitude }))
    const linha = new google.maps.Polyline({ path, strokeColor: '#3B82F6', strokeWeight: 3, map: gmap.current })
    overlays.current.push(linha)

    const bounds = new google.maps.LatLngBounds()
    path.forEach((pt) => bounds.extend(pt))

    eventos?.paradas.forEach((p) => {
      overlays.current.push(new google.maps.Marker({
        position: { lat: p.latitude, lng: p.longitude }, map: gmap.current,
        title: `Parada ${p.duracao_min} min`,
        icon: { path: google.maps.SymbolPath.CIRCLE, scale: 7, fillColor: '#EF4444', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 },
      }))
    })
    eventos?.excessos.forEach((e) => {
      overlays.current.push(new google.maps.Marker({
        position: { lat: e.latitude, lng: e.longitude }, map: gmap.current,
        title: `Excesso ${e.velocidade.toFixed(0)} km/h`,
        icon: { path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW, scale: 4, fillColor: '#F59E0B', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 1 },
      }))
    })
    gmap.current.fitBounds(bounds)
  }, [historico, eventos, pronto])

  async function baixar(formato: 'csv' | 'pdf') {
    if (!veiculoId) return
    setBaixando(true)
    try { await baixarEventos(veiculoId, de, ate, formato) } catch { toast.error('Erro ao gerar o relatório.') } finally { setBaixando(false) }
  }

  if (carregandoKey) return <AsyncState loading skeletonRows={3}>{null}</AsyncState>
  if (!key) {
    return <EmptyState icon={<RouteIcon />} title="Google Maps não configurado"
      description="Defina a chave do Google Maps em Configurações → Geral para ver o trajeto." />
  }

  return (
    <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
      <Card>
        <CardContent className="p-0 relative">
          <div ref={mapRef} className="h-[560px] w-full rounded-lg" />
          {erroMapa && <div className="absolute inset-0 grid place-items-center bg-card/80 text-sm text-destructive p-4 text-center">{erroMapa}</div>}
          {pronto && veiculoId && !carregandoHist && !historico?.length && (
            <div className="absolute inset-0 grid place-items-center bg-card/70 pointer-events-none">
              <EmptyState icon={<RouteIcon />} title="Sem trajeto" description="Nenhuma posição no período." />
            </div>
          )}
        </CardContent>
      </Card>

      <div className="space-y-4">
        <Card><CardContent className="pt-6 space-y-4">
          <Field label="Veículo">
            <Select value={veiculoId ? String(veiculoId) : undefined} onValueChange={(v) => setVeiculoId(Number(v))}>
              <SelectTrigger><SelectValue placeholder="Escolha o veículo…" /></SelectTrigger>
              <SelectContent>
                {veiculos?.map((v) => <SelectItem key={v.id} value={String(v.id)}>{v.placa}{v.descricao ? ` — ${v.descricao}` : ''}</SelectItem>)}
              </SelectContent>
            </Select>
          </Field>
          <div className="grid grid-cols-2 gap-2">
            <Field label="De"><Input type="date" value={de} onChange={(e) => setDe(e.target.value)} /></Field>
            <Field label="Até"><Input type="date" value={ate} onChange={(e) => setAte(e.target.value)} /></Field>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" disabled={!veiculoId || baixando} onClick={() => baixar('csv')}><Download size={15} /> CSV</Button>
            <Button variant="outline" size="sm" disabled={!veiculoId || baixando} onClick={() => baixar('pdf')}><Download size={15} /> PDF</Button>
          </div>
        </CardContent></Card>

        {veiculoId && eventos && (
          <div className="grid grid-cols-2 gap-3">
            <StatCard titulo="Paradas" valor={String(eventos.resumo.total_paradas)} icon={OctagonPause} accent="primary" hint=">5 min" />
            <StatCard titulo="Excessos" valor={String(eventos.resumo.total_excessos)} icon={Gauge} accent="primary"
              hint={eventos.veiculo.velocidade_maxima ? `> ${eventos.veiculo.velocidade_maxima} km/h` : 'sem limite'} />
          </div>
        )}
      </div>
    </div>
  )
}
