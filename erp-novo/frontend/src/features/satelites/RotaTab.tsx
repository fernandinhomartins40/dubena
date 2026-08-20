import { useEffect, useMemo, useRef, useState } from 'react'
import {
  Route as RouteIcon, Download, OctagonPause, Gauge, Clock, MapPin, Flag, Layers,
} from 'lucide-react'
import {
  Button, Card, CardContent, Field, Input, EmptyState, AsyncState, StatCard,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem, toast,
} from '@/components/ui'
import { carregarGoogleMaps } from '@/lib/googleMaps'
import {
  useVeiculos, useEventos, baixarEventos, useGoogleMapsKey, usePeriodoDisponivel,
  useViagens, type Viagem,
} from './extraApi'

const CENTRO_PADRAO = { lat: -25.3935, lng: -51.4562 }

/** Data local em ISO (yyyy-mm-dd). `toISOString` daria o dia em UTC. */
function dia(offset = 0): string {
  const d = new Date()
  d.setDate(d.getDate() + offset)

  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

/**
 * Atalhos de período.
 *
 * Digitar duas datas para ver o dia de ontem é trabalho que a tela pode poupar.
 * "7 dias" é o teto proposital: além disso a apuração varre dezenas de milhares
 * de posições e a lista de viagens fica grande demais para ser útil.
 */
const ATALHOS: { rotulo: string; de: () => string; ate: () => string }[] = [
  { rotulo: 'Hoje', de: () => dia(0), ate: () => dia(0) },
  { rotulo: 'Ontem', de: () => dia(-1), ate: () => dia(-1) },
  { rotulo: '7 dias', de: () => dia(-6), ate: () => dia(0) },
]

/** Cores das viagens — a selecionada usa a primeira, as demais ficam apagadas. */
const COR_ATIVA = '#FF6200'
const COR_INATIVA = '#94A3B8'

const hora = (iso: string) => new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
const dataCurta = (iso: string) => new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })

/** "1 h 20 min" — minutos crus viram conta de cabeça para quem lê. */
function duracao(min: number): string {
  if (min < 60) return `${min} min`
  const h = Math.floor(min / 60)

  return `${h} h ${min % 60 ? `${min % 60} min` : ''}`.trim()
}

/**
 * Aba Rota — trajeto por viagem, com paradas e excessos do período.
 *
 * O desenho anterior era o período inteiro numa linha só: um dia de entrega
 * passa pelas mesmas ruas várias vezes, e o emaranhado não dizia para onde o
 * veículo foi nem quando. Agora o dia vem partido em viagens (trechos entre
 * paradas de mais de 5 min) e clicar numa delas desenha só aquele trecho.
 *
 * O traçado liga as posições do GPS diretamente, sem passar pela Roads API do
 * Google: com reporte a cada ~30 s a linha já acompanha as ruas, e "grudar" no
 * asfalto custaria por bloco de 100 pontos sobre 16 milhões de posições.
 */
export function RotaTab() {
  const { data: key, isLoading: carregandoKey } = useGoogleMapsKey()
  const { data: veiculos } = useVeiculos()

  const [veiculoId, setVeiculoId] = useState<number | null>(null)
  const [de, setDe] = useState(dia(0))
  const [ate, setAte] = useState(dia(0))
  const [baixando, setBaixando] = useState(false)
  /** Índice da viagem em foco; null = todas desenhadas juntas. */
  const [foco, setFoco] = useState<number | null>(null)

  const { data: dados, isLoading: carregando } = useViagens(veiculoId, de, ate)
  const { data: eventos } = useEventos(veiculoId, de, ate)
  const { data: periodo } = usePeriodoDisponivel(veiculoId)

  const mapRef = useRef<HTMLDivElement>(null)
  const gmap = useRef<any>(null)
  const overlays = useRef<any[]>([])
  const [pronto, setPronto] = useState(false)
  const [erroMapa, setErroMapa] = useState<string | null>(null)

  const viagens = dados?.viagens ?? []

  // Trocar de veículo ou de período invalida a viagem em foco: o índice 3 do
  // dia anterior não é a mesma viagem do dia novo.
  useEffect(() => { setFoco(null) }, [veiculoId, de, ate])

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
      .catch((e) => setErroMapa(e?.message ?? 'Não foi possível carregar o Google Maps.'))
    return () => { vivo = false }
  }, [key])

  /** Desenha as viagens; a em foco fica destacada e as outras apagadas. */
  useEffect(() => {
    if (!pronto || !gmap.current) return
    const google = (window as any).google
    overlays.current.forEach((o) => o.setMap(null))
    overlays.current = []
    if (!viagens.length) return

    const bounds = new google.maps.LatLngBounds()

    viagens.forEach((v, i) => {
      const ativa = foco === null || foco === i
      const linha = new google.maps.Polyline({
        path: v.caminho,
        strokeColor: ativa ? COR_ATIVA : COR_INATIVA,
        strokeWeight: ativa ? 4 : 2,
        strokeOpacity: ativa ? 0.9 : 0.35,
        zIndex: ativa ? 10 : 1,
        map: gmap.current,
      })
      linha.addListener('click', () => setFoco(i))
      overlays.current.push(linha)

      // Só enquadra o que está em foco: com "todas" o mapa abre no período
      // inteiro, com uma selecionada ele aproxima naquele trecho.
      if (foco === null || foco === i) {
        v.caminho.forEach((p) => bounds.extend(p))
      }

      if (!ativa) return

      // Bandeira verde na saída, alfinete na chegada — sem isso não dá para
      // saber em que ponta o trajeto começou.
      overlays.current.push(new google.maps.Marker({
        position: v.origem, map: gmap.current,
        title: `Saída ${hora(v.inicio)}`,
        icon: {
          path: google.maps.SymbolPath.CIRCLE, scale: 7,
          fillColor: '#22C55E', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2,
        },
        zIndex: 20,
      }))
      overlays.current.push(new google.maps.Marker({
        position: v.destino, map: gmap.current,
        title: `Chegada ${hora(v.fim)}`,
        icon: {
          path: google.maps.SymbolPath.CIRCLE, scale: 7,
          fillColor: '#EF4444', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2,
        },
        zIndex: 20,
      }))
    })

    // Paradas e excessos só com o período inteiro à vista: sobre uma viagem
    // isolada eles pertencem a outros trechos e confundiriam a leitura.
    if (foco === null) {
      eventos?.paradas.forEach((p) => {
        overlays.current.push(new google.maps.Marker({
          position: { lat: p.latitude, lng: p.longitude }, map: gmap.current,
          title: `Parada ${p.duracao_min} min`,
          icon: {
            path: google.maps.SymbolPath.CIRCLE, scale: 5,
            fillColor: '#64748B', fillOpacity: 0.9, strokeColor: '#fff', strokeWeight: 1.5,
          },
        }))
      })
      eventos?.excessos.forEach((e) => {
        overlays.current.push(new google.maps.Marker({
          position: { lat: e.latitude, lng: e.longitude }, map: gmap.current,
          title: `Excesso ${e.velocidade.toFixed(0)} km/h`,
          icon: {
            path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW, scale: 4,
            fillColor: '#F59E0B', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 1,
          },
        }))
      })
    }

    if (!bounds.isEmpty()) gmap.current.fitBounds(bounds)
  }, [viagens, eventos, foco, pronto])

  async function baixar(formato: 'csv' | 'pdf') {
    if (!veiculoId) return
    setBaixando(true)
    try { await baixarEventos(veiculoId, de, ate, formato) } catch { toast.error('Erro ao gerar o relatório.') } finally { setBaixando(false) }
  }

  function aplicarAtalho(a: (typeof ATALHOS)[number]) {
    setDe(a.de())
    setAte(a.ate())
  }

  const atalhoAtivo = useMemo(
    () => ATALHOS.find((a) => a.de() === de && a.ate() === ate)?.rotulo ?? null,
    [de, ate],
  )

  /** Viagens agrupadas por dia — num período de 7 dias a lista fica ilegível sem isso. */
  const porDia = useMemo(() => {
    const mapa = new Map<string, { viagem: Viagem; indice: number }[]>()
    viagens.forEach((viagem, indice) => {
      const chave = viagem.inicio.slice(0, 10)
      if (!mapa.has(chave)) mapa.set(chave, [])
      mapa.get(chave)!.push({ viagem, indice })
    })

    return [...mapa.entries()]
  }, [viagens])

  if (carregandoKey) return <AsyncState loading skeletonRows={3}>{null}</AsyncState>
  if (!key) {
    return <EmptyState icon={<RouteIcon />} title="Google Maps não configurado"
      description="Defina a chave do Google Maps em Configurações → Geral para ver o trajeto." />
  }

  return (
    <div className="grid gap-4 lg:grid-cols-[1fr_340px]">
      <Card>
        <CardContent className="relative p-0">
          <div ref={mapRef} className="h-[560px] w-full rounded-lg" />
          {erroMapa && <div className="absolute inset-0 grid place-items-center bg-card/80 p-4 text-center text-sm text-destructive">{erroMapa}</div>}

          {/* Volta a ver o período todo depois de isolar uma viagem. */}
          {foco !== null && (
            <Button size="sm" variant="secondary" className="absolute left-3 top-3 shadow-md"
              onClick={() => setFoco(null)}>
              <Layers size={15} /> Ver todas as viagens
            </Button>
          )}

          {pronto && !carregando && veiculoId && !viagens.length && (
            <div className="absolute inset-0 grid place-items-center bg-card/80 p-4">
              <div className="max-w-sm text-center">
                <EmptyState
                  icon={<RouteIcon />}
                  title="Sem trajeto neste período"
                  description={
                    periodo?.fim
                      ? `Este veículo tem ${periodo.total.toLocaleString('pt-BR')} posições registradas, de ${periodo.inicio} até ${periodo.fim}.`
                      : 'Este veículo nunca reportou posição. Confira se o rastreador está vinculado ao veículo (campo IMEI).'
                  }
                />
                {periodo?.fim && periodo.fim !== ate && (
                  <Button size="sm" variant="secondary" className="mt-2"
                    onClick={() => { setDe(periodo.fim as string); setAte(periodo.fim as string) }}>
                    Ver o último dia com registro ({periodo.fim})
                  </Button>
                )}
              </div>
            </div>
          )}

          {!veiculoId && pronto && (
            <div className="pointer-events-none absolute inset-0 grid place-items-center bg-card/70">
              <EmptyState icon={<RouteIcon />} title="Escolha um veículo"
                description="Selecione o veículo e o período para ver o trajeto no mapa." />
            </div>
          )}
        </CardContent>
      </Card>

      <div className="space-y-4">
        <Card><CardContent className="space-y-3 pt-6">
          <Field label="Veículo">
            <Select value={veiculoId ? String(veiculoId) : undefined} onValueChange={(v) => setVeiculoId(Number(v))}>
              <SelectTrigger><SelectValue placeholder="Escolha o veículo…" /></SelectTrigger>
              <SelectContent>
                {veiculos?.map((v) => (
                  <SelectItem key={v.id} value={String(v.id)}>
                    {v.placa}{v.descricao ? ` — ${v.descricao}` : ''}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>

          <div className="flex gap-1">
            {ATALHOS.map((a) => (
              <Button
                key={a.rotulo} size="sm" className="flex-1 text-xs"
                variant={atalhoAtivo === a.rotulo ? 'default' : 'secondary'}
                onClick={() => aplicarAtalho(a)}
              >
                {a.rotulo}
              </Button>
            ))}
          </div>

          <div className="grid grid-cols-2 gap-2">
            <Field label="De"><Input type="date" value={de} onChange={(e) => setDe(e.target.value)} /></Field>
            <Field label="Até"><Input type="date" value={ate} onChange={(e) => setAte(e.target.value)} /></Field>
          </div>

          <div className="flex gap-2">
            <Button variant="outline" size="sm" disabled={!veiculoId || baixando} onClick={() => baixar('csv')}>
              <Download size={15} /> CSV
            </Button>
            <Button variant="outline" size="sm" disabled={!veiculoId || baixando} onClick={() => baixar('pdf')}>
              <Download size={15} /> PDF
            </Button>
          </div>
        </CardContent></Card>

        {veiculoId && dados && viagens.length > 0 && (
          <div className="grid grid-cols-2 gap-3">
            <StatCard titulo="Percorrido" valor={`${dados.resumo.distancia_km} km`}
              icon={RouteIcon} accent="primary" hint={`${dados.resumo.total} viagens`} />
            <StatCard titulo="Em rota" valor={duracao(dados.resumo.duracao_min)}
              icon={Clock} accent="primary" hint="tempo em deslocamento" />
          </div>
        )}

        {veiculoId && eventos && (
          <div className="grid grid-cols-2 gap-3">
            <StatCard titulo="Paradas" valor={String(eventos.resumo.total_paradas)}
              icon={OctagonPause} accent="primary" hint=">5 min" />
            <StatCard titulo="Excessos" valor={String(eventos.resumo.total_excessos)}
              icon={Gauge} accent="primary"
              hint={eventos.veiculo.velocidade_maxima ? `> ${eventos.veiculo.velocidade_maxima} km/h` : 'sem limite'} />
          </div>
        )}

        {/* Lista das viagens: é por aqui que se escolhe o trecho a ver. */}
        {viagens.length > 0 && (
          <Card><CardContent className="space-y-3 pt-4">
            <p className="text-xs font-medium text-muted-foreground">
              {viagens.length} viagem{viagens.length > 1 ? 'ns' : ''} no período
            </p>

            <div className="max-h-[340px] space-y-3 overflow-y-auto">
              {porDia.map(([data, itens]) => (
                <div key={data} className="space-y-1">
                  {porDia.length > 1 && (
                    <p className="sticky top-0 bg-card py-0.5 text-[11px] font-semibold text-muted-foreground">
                      {dataCurta(itens[0].viagem.inicio)}
                    </p>
                  )}

                  {itens.map(({ viagem, indice }) => (
                    <button
                      key={indice} type="button" onClick={() => setFoco(foco === indice ? null : indice)}
                      className={`w-full rounded-md border p-2 text-left transition-colors hover:bg-muted/60 ${
                        foco === indice ? 'border-primary bg-muted/40' : 'border-border'
                      }`}
                    >
                      <div className="flex items-center gap-1.5 text-sm">
                        <Flag size={12} className="shrink-0 text-emerald-600" />
                        <span className="font-medium tabular-nums">{hora(viagem.inicio)}</span>
                        <span className="text-muted-foreground">→</span>
                        <MapPin size={12} className="shrink-0 text-destructive" />
                        <span className="font-medium tabular-nums">{hora(viagem.fim)}</span>
                        <span className="ml-auto text-xs text-muted-foreground">
                          {duracao(viagem.duracao_min)}
                        </span>
                      </div>
                      <div className="mt-0.5 flex gap-3 pl-[18px] text-[11px] text-muted-foreground">
                        <span>{viagem.distancia_km.toFixed(1)} km</span>
                        <span>média {viagem.velocidade_media.toFixed(0)} km/h</span>
                        <span>máx {viagem.velocidade_maxima.toFixed(0)}</span>
                      </div>
                    </button>
                  ))}
                </div>
              ))}
            </div>
          </CardContent></Card>
        )}
      </div>
    </div>
  )
}
