import { useEffect, useMemo, useRef, useState } from 'react'
import { MapPin, Navigation, Search, Crosshair, Gauge, User, Clock } from 'lucide-react'
import { Card, CardContent, EmptyState, AsyncState, Input, Button } from '@/components/ui'
import { carregarGoogleMaps } from '@/lib/googleMaps'
import { dataHora } from '@/lib/format'
import { useUltimasPosicoes, useGoogleMapsKey, type UltimaPosicao } from './extraApi'
import {
  svgDoVeiculo, TAMANHO_MARCADOR, estadoDoVeiculo, CORES_ESTADO, ROTULOS_ESTADO,
  type EstadoVeiculo,
} from './iconesVeiculo'

// Centro padrão: Guarapuava/PR (fallback quando não há posição).
const CENTRO_PADRAO = { lat: -25.3935, lng: -51.4562 }

/** Há quanto tempo, em texto curto ("agora", "12 min", "3 h"). */
function desde(iso: string | null): string {
  if (!iso) return '—'
  const minutos = Math.floor((Date.now() - new Date(iso).getTime()) / 60000)
  if (minutos < 1) return 'agora'
  if (minutos < 60) return `${minutos} min`
  const horas = Math.floor(minutos / 60)
  if (horas < 24) return `${horas} h`

  return `${Math.floor(horas / 24)} d`
}

/**
 * Escapa texto que vai para dentro do HTML do card.
 *
 * A descrição e o motorista vêm do banco (e o auto-cadastro copia o apelido que
 * alguém digitou no Traccar). Sem escapar, um apelido com `<` quebraria o card
 * — e no limite injetaria markup na tela.
 */
function esc(texto: string): string {
  return texto.replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c] as string
  ))
}

/** Nome pelo qual quem opera reconhece o veículo. */
function nomeDe(p: UltimaPosicao): string {
  return p.descricao?.trim() || p.placa || `Veículo #${p.veiculo_id}`
}

/**
 * Mapa ao vivo da frota.
 *
 * Cada veículo é desenhado com a silhueta do seu tipo, girada para a direção em
 * que segue — antes eram todos o mesmo círculo cinza, e no meio de 23 aparelhos
 * não dava para saber qual era o caminhão nem para onde ia.
 *
 * A cor carrega o estado operacional (parado, ligado sem andar, em movimento,
 * acima da velocidade, sem sinal), e a lista lateral repete a mesma informação
 * em texto — quem acompanha a operação precisa ver a frota inteira de relance,
 * sem clicar em cada marcador.
 */
export function MapaAoVivoTab() {
  const { data: key, isLoading: carregandoKey } = useGoogleMapsKey()
  const { data: posicoes, isLoading } = useUltimasPosicoes()

  const mapRef = useRef<HTMLDivElement>(null)
  const gmap = useRef<any>(null)
  const markers = useRef<Map<number, any>>(new Map())
  const infoAberta = useRef<any>(null)
  const enquadrou = useRef(false)
  const [pronto, setPronto] = useState(false)
  const [erroMapa, setErroMapa] = useState<string | null>(null)
  const [selecionado, setSelecionado] = useState<number | null>(null)
  const [filtro, setFiltro] = useState('')

  // 1) Carrega o SDK e cria o mapa quando há key.
  useEffect(() => {
    if (!key || !mapRef.current || gmap.current) return
    let vivo = true
    carregarGoogleMaps(key)
      .then((google) => {
        if (!vivo || !mapRef.current) return
        gmap.current = new google.maps.Map(mapRef.current, {
          center: CENTRO_PADRAO, zoom: 12, mapTypeControl: false, streetViewControl: false,
        })
        setPronto(true)
      })
      .catch((e) => setErroMapa(e?.message ?? 'Não foi possível carregar o Google Maps. Verifique a chave em Configurações.'))
    return () => { vivo = false }
  }, [key])

  /** Lista com o estado já apurado, na ordem em que interessa ver. */
  const frota = useMemo(() => {
    const ordem: Record<EstadoVeiculo, number> = {
      excesso: 0, movimento: 1, ligado: 2, parado: 3, sem_sinal: 4,
    }
    const termo = filtro.trim().toLowerCase()

    return (posicoes ?? [])
      .map((p) => ({ ...p, estado: estadoDoVeiculo(p) }))
      .filter((p) => !termo
        || nomeDe(p).toLowerCase().includes(termo)
        || (p.placa ?? '').toLowerCase().includes(termo)
        || (p.motorista ?? '').toLowerCase().includes(termo))
      .sort((a, b) => ordem[a.estado] - ordem[b.estado] || nomeDe(a).localeCompare(nomeDe(b), 'pt-BR'))
  }, [posicoes, filtro])

  /**
   * 2) Atualiza os marcadores.
   *
   * Reaproveita o marcador existente em vez de recriar: com refresh a cada 30 s,
   * recriar fazia o ícone piscar e fechava o card que estivesse aberto.
   */
  useEffect(() => {
    if (!pronto || !gmap.current || !posicoes) return
    const google = (window as any).google
    const bounds = new google.maps.LatLngBounds()
    const vistos = new Set<number>()

    posicoes.forEach((p) => {
      const estado = estadoDoVeiculo(p)
      const pos = { lat: p.latitude, lng: p.longitude }
      vistos.add(p.veiculo_id)
      bounds.extend(pos)

      // Imagem SVG e nao `SymbolPath`: os icones da lucide sao tracados
      // abertos, e preenche-los como simbolo viraria uma mancha.
      const icone = {
        url: svgDoVeiculo(p.icone, p.tipo, CORES_ESTADO[estado], p.direcao),
        scaledSize: new google.maps.Size(TAMANHO_MARCADOR, TAMANHO_MARCADOR),
        anchor: new google.maps.Point(TAMANHO_MARCADOR / 2, TAMANHO_MARCADOR / 2),
      }

      const existente = markers.current.get(p.veiculo_id)
      if (existente) {
        existente.setPosition(pos)
        existente.setIcon(icone)
        existente.setTitle(`${nomeDe(p)} — ${ROTULOS_ESTADO[estado]}`)

        return
      }

      const marker = new google.maps.Marker({
        position: pos, map: gmap.current, icon: icone,
        title: `${nomeDe(p)} — ${ROTULOS_ESTADO[estado]}`,
      })
      marker.addListener('click', () => setSelecionado(p.veiculo_id))
      markers.current.set(p.veiculo_id, marker)
    })

    // Veículo que sumiu da resposta (desativado, trocado de empresa) tem de sair
    // do mapa, senão fica um fantasma parado para sempre.
    markers.current.forEach((m, id) => {
      if (!vistos.has(id)) { m.setMap(null); markers.current.delete(id) }
    })

    // Enquadra só na primeira carga: refazer a cada refresh desfaria o zoom que
    // o operador acabou de dar para acompanhar um veículo.
    if (!enquadrou.current && posicoes.length > 0) {
      gmap.current.fitBounds(bounds)
      enquadrou.current = true
    }
  }, [posicoes, pronto])

  /** Card do veículo selecionado, ancorado no marcador. */
  useEffect(() => {
    if (!pronto || !gmap.current) return
    const google = (window as any).google
    infoAberta.current?.close()
    if (selecionado === null) return

    const p = posicoes?.find((x) => x.veiculo_id === selecionado)
    const marker = markers.current.get(selecionado)
    if (!p || !marker) return

    const estado = estadoDoVeiculo(p)
    const linha = (rotulo: string, valor: string) =>
      `<div style="display:flex;justify-content:space-between;gap:16px;padding:2px 0">
         <span style="color:#6b7280">${rotulo}</span><strong>${valor}</strong></div>`

    const info = new google.maps.InfoWindow({
      content: `<div style="font-size:12px;min-width:210px;font-family:system-ui,sans-serif">
        <div style="font-weight:600;font-size:13px;margin-bottom:2px">${esc(nomeDe(p))}</div>
        <div style="display:inline-block;font-size:11px;padding:1px 7px;border-radius:9999px;
             background:${CORES_ESTADO[estado]}22;color:${CORES_ESTADO[estado]};margin-bottom:6px">
          ${ROTULOS_ESTADO[estado]}</div>
        ${p.placa ? linha('Placa', esc(p.placa)) : ''}
        ${p.motorista ? linha('Motorista', esc(p.motorista)) : ''}
        ${p.tipo ? linha('Tipo', esc(p.tipo)) : ''}
        ${linha('Velocidade', `${p.velocidade.toFixed(0)} km/h${p.velocidade_maxima ? ` / ${p.velocidade_maxima}` : ''}`)}
        ${linha('Ignição', p.ignicao ? 'Ligada' : 'Desligada')}
        ${linha('Atualizado', `${desde(p.registrado_em)} atrás`)}
        <div style="color:#9ca3af;font-size:11px;margin-top:4px">${dataHora(p.registrado_em)}</div>
      </div>`,
    })
    info.open(gmap.current, marker)
    info.addListener('closeclick', () => setSelecionado(null))
    infoAberta.current = info
  }, [selecionado, posicoes, pronto])

  /** Centraliza o mapa num veículo escolhido na lista. */
  function focar(p: UltimaPosicao) {
    setSelecionado(p.veiculo_id)
    if (!gmap.current) return
    gmap.current.panTo({ lat: p.latitude, lng: p.longitude })
    if (gmap.current.getZoom() < 15) gmap.current.setZoom(15)
  }

  const resumo = useMemo(() => {
    const contagem: Record<EstadoVeiculo, number> = {
      excesso: 0, movimento: 0, ligado: 0, parado: 0, sem_sinal: 0,
    }
    frota.forEach((p) => { contagem[p.estado]++ })

    return contagem
  }, [frota])

  if (carregandoKey) return <AsyncState loading skeletonRows={3}>{null}</AsyncState>
  if (!key) {
    return <EmptyState icon={<MapPin />} title="Google Maps não configurado"
      description="Defina a chave do Google Maps em Configurações → Geral para ver a frota no mapa." />
  }

  return (
    <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
      <Card>
        <CardContent className="relative p-0">
          <div ref={mapRef} className="h-[560px] w-full rounded-lg" />
          {erroMapa && <div className="absolute inset-0 grid place-items-center bg-card/80 p-4 text-center text-sm text-destructive">{erroMapa}</div>}

          {/* Contagem por estado: o operador vê de relance quantos estão
              rodando e quantos caíram, sem varrer a lista. */}
          {pronto && frota.length > 0 && (
            <div className="absolute bottom-3 left-3 flex flex-wrap gap-1.5 rounded-md bg-card/95 p-1.5 shadow-md">
              {(Object.keys(resumo) as EstadoVeiculo[])
                .filter((e) => resumo[e] > 0)
                .map((e) => (
                  <span key={e} className="flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px]">
                    <span className="h-2 w-2 rounded-full" style={{ background: CORES_ESTADO[e] }} />
                    {resumo[e]} {ROTULOS_ESTADO[e].toLowerCase()}
                  </span>
                ))}
            </div>
          )}

          {pronto && !isLoading && !posicoes?.length && (
            <div className="pointer-events-none absolute inset-0 grid place-items-center bg-card/70">
              <EmptyState icon={<Navigation />} title="Sem posições"
                description="Nenhum veículo reportou posição ainda." />
            </div>
          )}
        </CardContent>
      </Card>

      {/* Lista da frota — a mesma informação do mapa, legível de uma vez. */}
      <Card>
        <CardContent className="space-y-2 pt-4">
          <div className="relative">
            <Search size={14} className="absolute left-2.5 top-2.5 text-muted-foreground" />
            <Input value={filtro} onChange={(e) => setFiltro(e.target.value)}
              placeholder="Buscar veículo, placa ou motorista…" className="h-8 pl-8 text-sm" />
          </div>

          <div className="max-h-[500px] space-y-1 overflow-y-auto">
            {frota.map((p) => (
              <button
                key={p.veiculo_id} type="button" onClick={() => focar(p)}
                className={`w-full rounded-md border p-2 text-left text-sm transition-colors hover:bg-muted/60 ${
                  selecionado === p.veiculo_id ? 'border-primary bg-muted/40' : 'border-transparent'
                }`}
              >
                <div className="flex items-center gap-2">
                  <span className="h-2.5 w-2.5 shrink-0 rounded-full"
                    style={{ background: CORES_ESTADO[p.estado] }} />
                  <span className="truncate font-medium">{nomeDe(p)}</span>
                  {p.placa && <span className="ml-auto shrink-0 text-[11px] text-muted-foreground">{p.placa}</span>}
                </div>
                <div className="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 pl-[18px] text-[11px] text-muted-foreground">
                  <span className="flex items-center gap-1">
                    <Gauge size={11} />{p.velocidade.toFixed(0)} km/h
                  </span>
                  {p.motorista && (
                    <span className="flex items-center gap-1 truncate">
                      <User size={11} />{p.motorista}
                    </span>
                  )}
                  <span className="flex items-center gap-1">
                    <Clock size={11} />{desde(p.registrado_em)}
                  </span>
                </div>
              </button>
            ))}

            {frota.length === 0 && (
              <p className="py-6 text-center text-sm text-muted-foreground">
                {filtro ? 'Nenhum veículo com esse termo.' : 'Nenhum veículo reportou posição.'}
              </p>
            )}
          </div>

          {frota.length > 0 && (
            <Button variant="ghost" size="sm" className="w-full text-xs"
              onClick={() => { enquadrou.current = false; setSelecionado(null) }}>
              <Crosshair size={13} /> Enquadrar toda a frota
            </Button>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
