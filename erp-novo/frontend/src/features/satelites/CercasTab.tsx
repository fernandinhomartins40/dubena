import { useEffect, useRef, useState } from 'react'
import { MapPin, Trash2, Pencil, Plus, Save, X, Search } from 'lucide-react'
import {
  Button, Card, CardContent, Field, Input, EmptyState, AsyncState, ConfirmDialog, toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import { carregarGoogleMaps } from '@/lib/googleMaps'
import { useCercas, useSalvarCerca, useExcluirCerca, useGoogleMapsKey, type Cerca } from './extraApi'
import { useEmpresa } from '@/features/empresas/api'

// Centro padrão: Guarapuava/PR (fallback quando não há posição/cerca).
const CENTRO_PADRAO = { lat: -25.3935, lng: -51.4562 }
const CORES = ['#FF6200', '#22C55E', '#3B82F6', '#A855F7', '#EF4444', '#0EA5E9']

/** Aba Cercas (geofencing poligonal) — desenha o polígono no Google Maps, igual ao legado. */
export function CercasTab() {
  const { can, user } = useAuth()
  const podeEditar = can('monitora.edit')
  const { data: key, isLoading: carregandoKey } = useGoogleMapsKey()
  const { data: cercas, isLoading } = useCercas()
  // Para centralizar o mapa quando a empresa ainda não tem cerca nenhuma.
  const { data: empresa } = useEmpresa(user?.empresa_id ?? null)
  const salvar = useSalvarCerca()
  const excluir = useExcluirCerca()

  const mapRef = useRef<HTMLDivElement>(null)
  const gmap = useRef<any>(null)
  // Estado do desenho em refs: o listener do mapa é criado uma vez e precisa
  // enxergar o valor atual sem recriar o mapa a cada render.
  const modoDesenho = useRef(false)
  const corAtual = useRef(CORES[0])
  const marcadores = useRef<any[]>([]) // pinos numerados sobre os vértices
  const overlays = useRef<any[]>([]) // polígonos persistidos desenhados no mapa
  const rascunho = useRef<any>(null) // polígono recém-desenhado (ainda não salvo)
  const [pronto, setPronto] = useState(false)
  const [erroMapa, setErroMapa] = useState<string | null>(null)

  const [editando, setEditando] = useState<Cerca | null>(null)
  const [form, setForm] = useState<{ descricao: string; cor: string }>({ descricao: '', cor: CORES[0] })
  const [temRascunho, setTemRascunho] = useState(false)
  const [vertices, setVertices] = useState(0)
  const [excluindo, setExcluindo] = useState<Cerca | null>(null)
  const [busca, setBusca] = useState('')
  const [buscando, setBuscando] = useState(false)

  // 1) Carrega o SDK e cria o mapa quando há key.
  useEffect(() => {
    if (!key || !mapRef.current || gmap.current) return
    let vivo = true
    carregarGoogleMaps(key)
      .then((google) => {
        if (!vivo || !mapRef.current) return
        gmap.current = new google.maps.Map(mapRef.current, { center: CENTRO_PADRAO, zoom: 13, mapTypeControl: false, streetViewControl: false })

        // Desenho por CLIQUE no mapa, e não pelo `DrawingManager`: a Google
        // descontinuou o DrawingManager na versão 3.65 da Maps JavaScript API
        // (o aviso vermelho aparecia sobre o mapa). Um `Polygon` editável com
        // `addListener('click')` faz o mesmo — e sem depender da lib `drawing`.
        google.maps.event.addListener(gmap.current, 'click', (ev: any) => {
          if (!modoDesenho.current) return
          const ponto = { lat: ev.latLng.lat(), lng: ev.latLng.lng() }

          if (!rascunho.current) {
            rascunho.current = new google.maps.Polygon({
              paths: [ponto],
              fillColor: corAtual.current, fillOpacity: 0.25,
              strokeColor: corAtual.current, strokeWeight: 2,
              editable: true, map: gmap.current,
            })
            // O caminho muda também quando o usuário ARRASTA um vértice do
            // polígono editável — sem estes listeners o contador congelava e a
            // conferência do desenho ficava sem retorno.
            const path = rascunho.current.getPath()
            ;['insert_at', 'remove_at', 'set_at'].forEach((ev2) =>
              google.maps.event.addListener(path, ev2, () => {
                setVertices(path.getLength())
                redesenharMarcadores()
              }),
            )
            setTemRascunho(true)
          } else {
            rascunho.current.getPath().push(ev.latLng)
          }
          setVertices(rascunho.current.getPath().getLength())
          redesenharMarcadores()
        })
        setPronto(true)
      })
      .catch((e) => setErroMapa(e?.message ?? 'Não foi possível carregar o Google Maps. Verifique a chave em Configurações.'))
    return () => { vivo = false }
  }, [key]) // eslint-disable-line react-hooks/exhaustive-deps

  // 2) Redesenha as cercas persistidas sempre que a lista mudar.
  useEffect(() => {
    if (!pronto || !gmap.current || !cercas) return
    const google = (window as any).google
    overlays.current.forEach((o) => o.setMap(null))
    overlays.current = []
    const bounds = new google.maps.LatLngBounds()
    let temPonto = false
    cercas.forEach((c) => {
      if (!c.pontos?.length) return
      const path = c.pontos.map((p) => ({ lat: Number(p.latitude), lng: Number(p.longitude) }))
      const poly = new google.maps.Polygon({
        paths: path, fillColor: c.cor ?? '#FF6200', fillOpacity: 0.2,
        strokeColor: c.cor ?? '#FF6200', strokeWeight: 2, map: gmap.current,
      })
      poly.addListener('click', () => abrirEdicao(c))
      overlays.current.push(poly)
      path.forEach((pt) => { bounds.extend(pt); temPonto = true })
    })
    if (temPonto) {
      gmap.current.fitBounds(bounds)
      return
    }

    // Sem cerca alguma (revenda nova, ou primeira vez nesta empresa) o mapa
    // ficava parado em Guarapuava — quem opera em outra cidade não achava a
    // própria região e não tinha como começar a desenhar. Centraliza no
    // endereço da empresa; havendo só o nome da cidade, geocodifica.
    if (empresa?.latitude && empresa?.longitude) {
      gmap.current.setCenter({ lat: Number(empresa.latitude), lng: Number(empresa.longitude) })
      gmap.current.setZoom(13)
    } else if (empresa?.cidade) {
      new google.maps.Geocoder().geocode(
        { address: `${empresa.cidade}, ${empresa.uf ?? 'BR'}`, region: 'BR' },
        (res: any, status: string) => {
          if (status === 'OK' && res?.[0] && gmap.current) {
            gmap.current.setCenter(res[0].geometry.location)
            gmap.current.setZoom(12)
          }
        },
      )
    }
  }, [cercas, pronto, empresa]) // eslint-disable-line react-hooks/exhaustive-deps

  /**
   * Marcadores numerados sobre cada vértice.
   *
   * O polígono editável do Google mostra alças pequenas e sem ordem — em cerca
   * com dezenas de pontos não dá para saber qual é qual, nem conferir se o
   * traçado seguiu a rua certa. O número mostra a sequência; o clique remove o
   * ponto, que era a operação que faltava para corrigir um clique errado sem
   * recomeçar o desenho inteiro.
   */
  function redesenharMarcadores() {
    const google = (window as any).google
    marcadores.current.forEach((m) => m.setMap(null))
    marcadores.current = []
    if (!rascunho.current || !gmap.current) return

    const path = rascunho.current.getPath()
    for (let i = 0; i < path.getLength(); i++) {
      const pos = path.getAt(i)
      const m = new google.maps.Marker({
        position: pos,
        map: gmap.current,
        label: { text: String(i + 1), color: '#fff', fontSize: '11px', fontWeight: '600' },
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          scale: 9,
          fillColor: corAtual.current,
          fillOpacity: 1,
          strokeColor: '#fff',
          strokeWeight: 2,
        },
        title: `Vértice ${i + 1} — clique para remover`,
        zIndex: 999,
      })
      m.addListener('click', () => {
        if (path.getLength() <= 1) return
        path.removeAt(i)
        setVertices(path.getLength())
        redesenharMarcadores()
      })
      marcadores.current.push(m)
    }
  }

  /** Move o mapa para o endereço digitado (geocodificação do próprio SDK). */
  function irParaEndereco() {
    const termo = busca.trim()
    if (!termo || !gmap.current) return
    setBuscando(true)
    new (window as any).google.maps.Geocoder().geocode(
      { address: termo, region: 'BR' },
      (res: any, status: string) => {
        setBuscando(false)
        if (status !== 'OK' || !res?.[0]) { toast.error('Endereço não encontrado.'); return }
        const g = res[0].geometry
        // `viewport` respeita o tamanho do lugar: uma cidade não deve abrir
        // com o mesmo zoom de uma rua.
        if (g.viewport) gmap.current.fitBounds(g.viewport)
        else { gmap.current.setCenter(g.location); gmap.current.setZoom(15) }
      },
    )
  }

  function limparRascunho() {
    if (rascunho.current) { rascunho.current.setMap(null); rascunho.current = null }
    marcadores.current.forEach((m) => m.setMap(null))
    marcadores.current = []
    setVertices(0)
    setTemRascunho(false)
  }

  function iniciarDesenho() {
    setEditando(null)
    setForm({ descricao: '', cor: CORES[0] })
    limparRascunho()
    corAtual.current = CORES[0]
    modoDesenho.current = true
    setVertices(0)
  }

  function abrirEdicao(c: Cerca) {
    setEditando(c)
    setForm({ descricao: c.descricao, cor: c.cor ?? CORES[0] })
    limparRascunho()
    // Carrega os pontos da cerca como rascunho editável.
    const google = (window as any).google
    const path = c.pontos.map((p) => ({ lat: Number(p.latitude), lng: Number(p.longitude) }))
    modoDesenho.current = false
    corAtual.current = c.cor ?? CORES[0]
    rascunho.current = new google.maps.Polygon({ paths: path, fillColor: c.cor ?? CORES[0], fillOpacity: 0.3, strokeColor: c.cor ?? CORES[0], strokeWeight: 2, editable: true, map: gmap.current })
    const gpath = rascunho.current.getPath()
    ;['insert_at', 'remove_at', 'set_at'].forEach((ev) =>
      google.maps.event.addListener(gpath, ev, () => { setVertices(gpath.getLength()); redesenharMarcadores() }),
    )
    setTemRascunho(true)
    setVertices(gpath.getLength())
    redesenharMarcadores()
  }

  function pontosDoRascunho(): { latitude: number; longitude: number }[] {
    if (!rascunho.current) return []
    const arr: { latitude: number; longitude: number }[] = []
    rascunho.current.getPath().forEach((latlng: any) => arr.push({ latitude: latlng.lat(), longitude: latlng.lng() }))
    return arr
  }

  async function onSalvar() {
    if (!form.descricao.trim()) { toast.error('Informe o nome da cerca.'); return }
    const pontos = pontosDoRascunho()
    if (pontos.length < 3) { toast.error('Desenhe um polígono com ao menos 3 pontos.'); return }
    try {
      await salvar.mutateAsync({ id: editando?.id, descricao: form.descricao, cor: form.cor, pontos })
      toast.success(editando ? 'Cerca atualizada.' : 'Cerca criada.')
      cancelar()
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao salvar a cerca.')
    }
  }

  function cancelar() {
    limparRascunho()
    setEditando(null)
    setForm({ descricao: '', cor: CORES[0] })
    modoDesenho.current = false
    setVertices(0)
  }

  const editandoAlgo = temRascunho || editando !== null

  if (carregandoKey) return <AsyncState loading skeletonRows={3}>{null}</AsyncState>
  if (!key) {
    return <EmptyState icon={<MapPin />} title="Google Maps não configurado"
      description="Defina a chave do Google Maps em Configurações → Geral para desenhar cercas no mapa." />
  }

  return (
    <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
      {/* Mapa */}
      <Card>
        <CardContent className="p-0 relative">
          <div ref={mapRef} className="h-[560px] w-full rounded-lg" />
          {erroMapa && <div className="absolute inset-0 grid place-items-center bg-card/80 text-sm text-destructive p-4 text-center">{erroMapa}</div>}
          {podeEditar && pronto && !editandoAlgo && (
            <Button className="absolute left-3 top-3 shadow-md" onClick={iniciarDesenho}><Plus size={16} /> Desenhar cerca</Button>
          )}
          {/* Ir para um endereço: quem está começando não tem cerca nem, às
              vezes, endereço da empresa cadastrado — sem isto o mapa ficava
              num lugar qualquer e não havia como chegar à região de atuação. */}
          {pronto && (
            <form
              onSubmit={(e) => { e.preventDefault(); irParaEndereco() }}
              className="absolute right-3 top-3 flex gap-1 rounded-md bg-card/95 p-1 shadow-md"
            >
              <Input
                value={busca} onChange={(e) => setBusca(e.target.value)}
                placeholder="Ir para endereço ou cidade…" className="h-8 w-56 text-sm"
              />
              <Button type="submit" size="sm" variant="secondary" loading={buscando}>
                <Search size={15} />
              </Button>
            </form>
          )}
        </CardContent>
      </Card>

      {/* Painel lateral */}
      <div className="space-y-4">
        {editandoAlgo ? (
          <Card><CardContent className="pt-6 space-y-4">
            <div className="flex items-center justify-between">
              <p className="font-medium">{editando ? 'Editar cerca' : 'Nova cerca'}</p>
              <Button variant="ghost" size="icon" onClick={cancelar}><X size={16} /></Button>
            </div>
            {/* Instruções completas: o desenho é preciso na medida em que o
                usuário sabe que pode ajustar e remover pontos. Sem dizer isso,
                um clique errado levava a recomeçar a cerca inteira. */}
            {!temRascunho ? (
              <div className="rounded-md bg-secondary/60 p-3 text-xs text-muted-foreground space-y-1">
                <p className="font-medium text-foreground">Como desenhar</p>
                <p>1. Clique no mapa em cada esquina do contorno.</p>
                <p>2. Arraste um ponto para ajustar; clique nele para remover.</p>
                <p>3. Use o zoom para seguir as ruas com precisão.</p>
              </div>
            ) : (
              <div className="rounded-md bg-secondary/60 p-3 text-xs space-y-1">
                <p className="font-medium text-foreground">
                  {vertices} vértice(s)
                  {vertices < 3 && <span className="text-destructive"> — mínimo de 3</span>}
                </p>
                <p className="text-muted-foreground">
                  Continue clicando no mapa para adicionar. Arraste um ponto para
                  ajustar, ou clique nele para remover.
                </p>
              </div>
            )}
            <Field label="Nome" required><Input autoFocus value={form.descricao} onChange={(e) => setForm((f) => ({ ...f, descricao: e.target.value }))} placeholder="Ex.: Zona Centro" /></Field>
            <Field label="Cor">
              <div className="flex gap-2 flex-wrap">
                {CORES.map((c) => (
                  <button key={c} type="button" aria-label={`Cor ${c}`} onClick={() => setForm((f) => ({ ...f, cor: c }))}
                    className={`size-7 rounded-full border-2 transition ${form.cor === c ? 'border-foreground scale-110' : 'border-transparent'}`} style={{ background: c }} />
                ))}
              </div>
            </Field>
            <div className="flex gap-2">
              <Button onClick={onSalvar} loading={salvar.isPending} disabled={!temRascunho || vertices < 3}><Save size={16} /> Salvar</Button>
              <Button variant="outline" onClick={cancelar}>Cancelar</Button>
            </div>
          </CardContent></Card>
        ) : (
          <Card><CardContent className="pt-6">
            <p className="font-medium mb-1">Cercas cadastradas</p>
            <AsyncState loading={isLoading} empty={!cercas?.length} emptyIcon={<MapPin />} emptyTitle="Nenhuma cerca"
              emptyDescription={podeEditar ? 'Clique em “Desenhar cerca” para criar a primeira.' : undefined}>
              <div className="divide-y divide-border -mx-2">
                {cercas?.map((c) => (
                  <div key={c.id} className="flex items-center justify-between gap-2 px-2 py-2">
                    <button className="flex items-center gap-2 min-w-0 text-left" onClick={() => abrirEdicao(c)}>
                      <span className="size-3 shrink-0 rounded-full" style={{ background: c.cor ?? '#FF6200' }} />
                      <span className="text-sm truncate">{c.descricao}</span>
                      <span className="text-xs text-muted-foreground shrink-0">{c.pontos?.length ?? 0} pts</span>
                    </button>
                    {podeEditar && (
                      <div className="flex shrink-0">
                        <Button variant="ghost" size="icon" onClick={() => abrirEdicao(c)}><Pencil size={15} /></Button>
                        <Button variant="ghost" size="icon" onClick={() => setExcluindo(c)}><Trash2 size={15} /></Button>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </AsyncState>
          </CardContent></Card>
        )}
      </div>

      <ConfirmDialog
        open={!!excluindo} onOpenChange={(o) => !o && setExcluindo(null)}
        title="Excluir cerca"
        description={<>Excluir a cerca <strong>{excluindo?.descricao}</strong>?</>}
        loading={excluir.isPending}
        onConfirm={async () => { try { await excluir.mutateAsync(excluindo!.id); toast.success('Cerca excluída.') } catch { toast.error('Não foi possível excluir.') } finally { setExcluindo(null) } }}
      />
    </div>
  )
}
