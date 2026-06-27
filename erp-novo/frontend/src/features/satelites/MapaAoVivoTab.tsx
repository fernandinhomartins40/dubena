import { useEffect, useRef, useState } from 'react'
import { MapPin, Navigation } from 'lucide-react'
import { Card, CardContent, EmptyState, AsyncState } from '@/components/ui'
import { carregarGoogleMaps } from '@/lib/googleMaps'
import { dataHora } from '@/lib/format'
import { useUltimasPosicoes, useGoogleMapsKey } from './extraApi'

// Centro padrão: Guarapuava/PR (fallback quando não há posição).
const CENTRO_PADRAO = { lat: -25.3935, lng: -51.4562 }

/**
 * Mapa ao vivo da frota (F1) — marcadores das últimas posições no Google Maps,
 * atualizando a cada 30s (refetchInterval do hook). Verde = ignição ligada.
 */
export function MapaAoVivoTab() {
  const { data: key, isLoading: carregandoKey } = useGoogleMapsKey()
  const { data: posicoes, isLoading } = useUltimasPosicoes()

  const mapRef = useRef<HTMLDivElement>(null)
  const gmap = useRef<any>(null)
  const markers = useRef<any[]>([])
  const [pronto, setPronto] = useState(false)
  const [erroMapa, setErroMapa] = useState<string | null>(null)

  // 1) Carrega o SDK e cria o mapa quando há key.
  useEffect(() => {
    if (!key || !mapRef.current || gmap.current) return
    let vivo = true
    carregarGoogleMaps(key)
      .then((google) => {
        if (!vivo || !mapRef.current) return
        gmap.current = new google.maps.Map(mapRef.current, { center: CENTRO_PADRAO, zoom: 12, mapTypeControl: false, streetViewControl: false })
        setPronto(true)
      })
      .catch((e) => setErroMapa(e?.message ?? 'Não foi possível carregar o Google Maps. Verifique a chave em Configurações.'))
    return () => { vivo = false }
  }, [key])

  // 2) Redesenha os marcadores sempre que as posições mudarem.
  useEffect(() => {
    if (!pronto || !gmap.current || !posicoes) return
    const google = (window as any).google
    markers.current.forEach((m) => m.setMap(null))
    markers.current = []
    const bounds = new google.maps.LatLngBounds()
    let tem = false
    posicoes.forEach((p) => {
      const pos = { lat: p.latitude, lng: p.longitude }
      const marker = new google.maps.Marker({
        position: pos, map: gmap.current, title: `${p.placa ?? `#${p.veiculo_id}`} — ${p.velocidade.toFixed(0)} km/h`,
        icon: {
          path: google.maps.SymbolPath.CIRCLE, scale: 7,
          fillColor: p.ignicao ? '#22C55E' : '#9CA3AF', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2,
        },
      })
      const info = new google.maps.InfoWindow({
        content: `<div style="font-size:12px"><strong>${p.placa ?? `#${p.veiculo_id}`}</strong><br/>${p.velocidade.toFixed(0)} km/h · ${p.ignicao ? 'Ligada' : 'Desligada'}<br/>${dataHora(p.registrado_em)}</div>`,
      })
      marker.addListener('click', () => info.open(gmap.current, marker))
      markers.current.push(marker)
      bounds.extend(pos); tem = true
    })
    if (tem) gmap.current.fitBounds(bounds)
  }, [posicoes, pronto])

  if (carregandoKey) return <AsyncState loading skeletonRows={3}>{null}</AsyncState>
  if (!key) {
    return <EmptyState icon={<MapPin />} title="Google Maps não configurado"
      description="Defina a chave do Google Maps em Configurações → Geral para ver a frota no mapa." />
  }

  return (
    <Card>
      <CardContent className="p-0 relative">
        <div ref={mapRef} className="h-[560px] w-full rounded-lg" />
        {erroMapa && <div className="absolute inset-0 grid place-items-center bg-card/80 text-sm text-destructive p-4 text-center">{erroMapa}</div>}
        {pronto && !isLoading && !posicoes?.length && (
          <div className="absolute inset-0 grid place-items-center bg-card/70 pointer-events-none">
            <EmptyState icon={<Navigation />} title="Sem posições" description="Nenhum veículo reportou posição ainda." />
          </div>
        )}
      </CardContent>
    </Card>
  )
}
