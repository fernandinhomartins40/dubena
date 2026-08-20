import { useEffect, useMemo, useRef, useState } from 'react'
import {
  MapPin, Trash2, Pencil, Plus, Save, X, Search, ChevronRight,
  Undo2, Redo2, Spline, Minimize2, Copy, CornerUpRight,
  Wand2, Blocks, AlertTriangle, Check,
} from 'lucide-react'
import {
  Button, Card, CardContent, Field, Input, EmptyState, AsyncState, ConfirmDialog, toast,
  AsyncSelect,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import { carregarGoogleMaps } from '@/lib/googleMaps'
import {
  useCercas, useSalvarCerca, useExcluirCerca, useGoogleMapsKey,
  useQuadraDaCerca, useAjustarCerca, useConflitosDeCerca, type Cerca,
} from './extraApi'
import { useEmpresa } from '@/features/empresas/api'
import { useEditorCerca } from './useEditorCerca'
import type { Ponto } from './editorPoligono'

// Centro padrão: Guarapuava/PR (fallback quando não há posição/cerca).
const CENTRO_PADRAO = { lat: -25.3935, lng: -51.4562 }
const CORES = ['#FF6200', '#22C55E', '#3B82F6', '#A855F7', '#EF4444', '#0EA5E9']
/** Rótulo do grupo das cercas ainda não classificadas. */
const SEM_MUNICIPIO = 'Sem município'

/** Aba Cercas (geofencing poligonal) — desenha o polígono no Google Maps. */
export function CercasTab() {
  const { can, user } = useAuth()
  const podeEditar = can('monitora.edit')
  const { data: key, isLoading: carregandoKey } = useGoogleMapsKey()
  const { data: cercas, isLoading } = useCercas()
  const { data: empresa } = useEmpresa(user?.empresa_id ?? null)
  const salvar = useSalvarCerca()
  const excluir = useExcluirCerca()

  const mapRef = useRef<HTMLDivElement>(null)
  const gmap = useRef<any>(null)
  const overlays = useRef<any[]>([]) // polígonos persistidos desenhados no mapa
  const enquadrou = useRef(false) // o mapa já foi enquadrado nas cercas uma vez?
  const modoDesenho = useRef(false)
  const corAtual = useRef(CORES[0])
  const [pronto, setPronto] = useState(false)
  const [erroMapa, setErroMapa] = useState<string | null>(null)

  const [editando, setEditando] = useState<Cerca | null>(null)
  const [form, setForm] = useState<{
    descricao: string; cor: string; cidade_id?: number | null; cidadeLabel?: string | null
  }>({ descricao: '', cor: CORES[0] })
  const [temRascunho, setTemRascunho] = useState(false)
  const [excluindo, setExcluindo] = useState<Cerca | null>(null)
  const [selecionada, setSelecionada] = useState<number | null>(null)
  const [filtro, setFiltro] = useState('')
  const [recolhidos, setRecolhidos] = useState<string[]>([])
  const [busca, setBusca] = useState('')
  const [buscando, setBuscando] = useState(false)

  // Ferramentas assistidas. `modoQuadra` faz o clique no mapa fechar o
  // quarteirao em vez de acrescentar um vertice — sao gestos incompativeis, por
  // isso um flag e nao dois botoes que valem ao mesmo tempo.
  const [modoQuadra, setModoQuadra] = useState(false)
  const [verConflitos, setVerConflitos] = useState(false)
  const quadraMut = useQuadraDaCerca()
  const ajusteMut = useAjustarCerca()
  const { data: conflitos, isLoading: carregandoConflitos } = useConflitosDeCerca()

  /**
   * Vértices das OUTRAS cercas — alvo do snap.
   *
   * Grudar na borda vizinha é o que impede buraco e sobreposição entre setores
   * adjacentes: sem isso o geofencing acaba com endereços em nenhuma cerca ou
   * em duas.
   */
  const vizinhos = useMemo<Ponto[]>(() => {
    const saida: Ponto[] = []
    for (const c of cercas ?? []) {
      if (editando?.id === c.id) continue
      for (const p of c.pontos ?? []) {
        saida.push({ lat: Number(p.latitude), lng: Number(p.longitude) })
      }
    }

    return saida
  }, [cercas, editando])

  const editor = useEditorCerca({ vizinhos })
  // O listener de clique do mapa é registrado uma única vez e capturaria o
  // `editor` da primeira renderização. A ref dá acesso sempre ao atual.
  const editorRef = useRef(editor)
  editorRef.current = editor
  // O listener de clique do mapa e registrado uma unica vez e capturaria o
  // valor da primeira renderizacao; a ref da acesso sempre ao atual.
  const modoQuadraRef = useRef(modoQuadra)
  modoQuadraRef.current = modoQuadra
  const pegarQuadraRef = useRef<(p: Ponto) => void>(() => {})

  // 1) Carrega o SDK e cria o mapa quando há key.
  useEffect(() => {
    if (!key || !mapRef.current || gmap.current) return
    let vivo = true
    carregarGoogleMaps(key)
      .then((google) => {
        if (!vivo || !mapRef.current) return
        gmap.current = new google.maps.Map(mapRef.current, {
          center: CENTRO_PADRAO, zoom: 13, mapTypeControl: false, streetViewControl: false,
        })

        // Desenho por CLIQUE no mapa, e não pelo `DrawingManager`: a Google o
        // descontinuou na versão 3.65 da Maps JavaScript API.
        google.maps.event.addListener(gmap.current, 'click', (ev: any) => {
          if (!modoDesenho.current) return
          const ponto = { lat: ev.latLng.lat(), lng: ev.latLng.lng() }

          // Modo quadra: o clique fecha o quarteirao pelas ruas em volta, em
          // vez de acrescentar um vertice solto.
          if (modoQuadraRef.current) {
            pegarQuadraRef.current(ponto)

            return
          }

          if (!editorRef.current.ativo) {
            editorRef.current.abrir(gmap.current, [ponto], corAtual.current)
            setTemRascunho(true)
          } else {
            editorRef.current.acrescentar(ponto)
          }
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
      // A cerca em edição vira polígono editável do editor: desenhar as duas
      // deixaria a área duplicada no mapa.
      if (editando?.id === c.id) return
      const path = c.pontos.map((p) => ({ lat: Number(p.latitude), lng: Number(p.longitude) }))
      const destacada = selecionada === c.id
      const poly = new google.maps.Polygon({
        paths: path,
        fillColor: c.cor ?? '#FF6200',
        // A selecionada fica mais opaca e com traço mais grosso: num mapa com
        // 19 áreas sobrepostas, cor sozinha não diz qual está em foco.
        fillOpacity: destacada ? 0.45 : 0.15,
        strokeColor: c.cor ?? '#FF6200',
        strokeWeight: destacada ? 4 : 2,
        zIndex: destacada ? 10 : 1,
        map: gmap.current,
      })
      poly.addListener('click', () => focarCerca(c))
      overlays.current.push(poly)
      path.forEach((pt) => { bounds.extend(pt); temPonto = true })
    })

    if (temPonto) {
      // Enquadra todas apenas na PRIMEIRA carga: refazer isso a cada render
      // desfaria o zoom que o usuário acabou de dar ao clicar numa cerca.
      if (!enquadrou.current) {
        gmap.current.fitBounds(bounds)
        enquadrou.current = true
      }

      return
    }

    // Sem cerca alguma (revenda nova, ou primeira vez nesta empresa) o mapa
    // ficava parado em Guarapuava — quem opera em outra cidade não achava a
    // própria região e não tinha como começar a desenhar.
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
  }, [cercas, pronto, empresa, selecionada, editando]) // eslint-disable-line react-hooks/exhaustive-deps

  // Ctrl+Z / Ctrl+Y enquanto edita: é o atalho que todo editor gráfico tem, e
  // sem ele um arraste errado custava redesenhar o contorno.
  useEffect(() => {
    if (!temRascunho) return
    const atalho = (e: KeyboardEvent) => {
      if (!(e.ctrlKey || e.metaKey)) return
      if (e.key === 'z' && !e.shiftKey) { e.preventDefault(); editor.desfazer() }
      if (e.key === 'y' || (e.key === 'z' && e.shiftKey)) { e.preventDefault(); editor.refazer() }
    }
    window.addEventListener('keydown', atalho)

    return () => window.removeEventListener('keydown', atalho)
  }, [temRascunho, editor])

  /**
   * Aproxima o mapa na cerca e a destaca — SEM entrar em edição.
   *
   * Clicar na lista ou no polígono é "quero ver onde fica"; editar é uma
   * decisão à parte, pelo lápis.
   */
  function focarCerca(c: Cerca) {
    if (!gmap.current || !c.pontos?.length) return
    const google = (window as any).google
    const bounds = new google.maps.LatLngBounds()
    c.pontos.forEach((p) => bounds.extend({ lat: Number(p.latitude), lng: Number(p.longitude) }))
    gmap.current.fitBounds(bounds, 48)
    setSelecionada(c.id)
  }

  /**
   * Fecha o quarteirao onde o operador clicou.
   *
   * Sem contorno aberto, a quadra VIRA o contorno; com contorno aberto, ela se
   * SOMA ao que ja existe. E o que permite montar um setor clicando quadra a
   * quadra, sem ter que decidir antes qual dos dois comportamentos se quer.
   */
  async function pegarQuadra(ponto: Ponto) {
    try {
      const quadra = await quadraMut.mutateAsync(ponto)
      if (!quadra || quadra.length < 3) {
        toast.error('Nao consegui fechar a quadra aqui — as ruas em volta podem nao estar mapeadas.')

        return
      }

      if (!editorRef.current.ativo) {
        editorRef.current.abrir(gmap.current, quadra, corAtual.current)
        setTemRascunho(true)
      } else {
        editorRef.current.unir(quadra)
      }
      toast.success('Quadra adicionada — clique em outra para somar, ou ajuste os pontos.')
    } catch {
      toast.error('Nao foi possivel consultar a malha de ruas.')
    }
  }
  pegarQuadraRef.current = pegarQuadra

  /**
   * A vareta magica: encaixa o contorno da cerca nas ruas.
   *
   * Abre a cerca em edicao com o traçado sugerido JA aplicado, mas nao grava —
   * o operador confere no mapa e salva (ou cancela). Um encaixe ruim nunca
   * entra sozinho no geofencing, e Ctrl+Z devolve o contorno original.
   */
  async function aplicarVareta(c: Cerca) {
    try {
      const ajustado = await ajusteMut.mutateAsync(c.id)
      if (!ajustado || ajustado.length < 3) {
        toast.error('Nao foi possivel ajustar este contorno.')

        return
      }

      abrirEdicao(c)
      // Depois de `abrirEdicao`, que ja abriu o contorno original: substituir
      // entra como UM passo no historico, entao Ctrl+Z volta ao que era.
      editorRef.current.substituir(ajustado)
      toast.success('Contorno encaixado nas ruas — confira e salve, ou Ctrl+Z para voltar.')
    } catch {
      toast.error('Nao foi possivel ajustar o contorno.')
    }
  }

  function limparRascunho() {
    editor.fechar()
    setTemRascunho(false)
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
        // `viewport` respeita o tamanho do lugar: uma cidade não deve abrir com
        // o mesmo zoom de uma rua.
        if (g.viewport) gmap.current.fitBounds(g.viewport)
        else { gmap.current.setCenter(g.location); gmap.current.setZoom(15) }
      },
    )
  }

  function iniciarDesenho() {
    setEditando(null)
    setSelecionada(null)
    setForm({ descricao: '', cor: CORES[0], cidade_id: null, cidadeLabel: null })
    limparRascunho()
    corAtual.current = CORES[0]
    modoDesenho.current = true
    setModoQuadra(false)
  }

  function abrirEdicao(c: Cerca) {
    setSelecionada(null)
    setEditando(c)
    setForm({
      descricao: c.descricao, cor: c.cor ?? CORES[0],
      cidade_id: c.cidade_id, cidadeLabel: c.cidade ? `${c.cidade}${c.uf ? `/${c.uf}` : ''}` : null,
    })
    modoDesenho.current = true // continuar clicando acrescenta vértices
    corAtual.current = c.cor ?? CORES[0]
    editor.abrir(
      gmap.current,
      c.pontos.map((p) => ({ lat: Number(p.latitude), lng: Number(p.longitude) })),
      c.cor ?? CORES[0],
    )
    setTemRascunho(true)
    focarCerca(c)
    setSelecionada(null)
  }

  /** Duplica uma cerca como base para desenhar a vizinha. */
  function duplicar(c: Cerca) {
    setEditando(null)
    setSelecionada(null)
    setForm({
      descricao: `${c.descricao} (cópia)`, cor: c.cor ?? CORES[0],
      cidade_id: c.cidade_id, cidadeLabel: c.cidade ? `${c.cidade}${c.uf ? `/${c.uf}` : ''}` : null,
    })
    modoDesenho.current = true
    corAtual.current = c.cor ?? CORES[0]
    editor.abrir(
      gmap.current,
      c.pontos.map((p) => ({ lat: Number(p.latitude), lng: Number(p.longitude) })),
      c.cor ?? CORES[0],
    )
    setTemRascunho(true)
    toast.success('Contorno copiado — ajuste e salve como nova cerca.')
  }

  async function onSalvar() {
    if (!form.descricao.trim()) { toast.error('Informe o nome da cerca.'); return }
    const pontos = editor.lerPontos().map((p) => ({ latitude: p.lat, longitude: p.lng }))
    if (pontos.length < 3) { toast.error('Desenhe um polígono com ao menos 3 pontos.'); return }
    try {
      await salvar.mutateAsync({
        id: editando?.id, descricao: form.descricao, cor: form.cor,
        cidade_id: form.cidade_id ?? null, pontos,
      })
      toast.success(editando ? 'Cerca atualizada.' : 'Cerca criada.')
      cancelar()
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao salvar a cerca.')
    }
  }

  function cancelar() {
    setSelecionada(null)
    limparRascunho()
    setEditando(null)
    setForm({ descricao: '', cor: CORES[0], cidade_id: null, cidadeLabel: null })
    modoDesenho.current = false
    setModoQuadra(false)
  }

  /**
   * Cercas por município, filtradas.
   *
   * "Sem município" vai por ÚLTIMO e não é escondido: é a fila de trabalho de
   * quem precisa classificar as cercas herdadas.
   */
  const grupos = useMemo(() => {
    const termo = filtro.trim().toLowerCase()
    const mapa = new Map<string, Cerca[]>()

    for (const c of cercas ?? []) {
      const municipio = c.cidade ? `${c.cidade}${c.uf ? `/${c.uf}` : ''}` : SEM_MUNICIPIO
      if (termo && !c.descricao.toLowerCase().includes(termo) && !municipio.toLowerCase().includes(termo)) continue
      if (!mapa.has(municipio)) mapa.set(municipio, [])
      mapa.get(municipio)!.push(c)
    }

    return [...mapa.entries()].sort(([a], [b]) => {
      if (a === SEM_MUNICIPIO) return 1
      if (b === SEM_MUNICIPIO) return -1
      return a.localeCompare(b, 'pt-BR')
    })
  }, [cercas, filtro])

  function alternarGrupo(municipio: string) {
    setRecolhidos((r) => (r.includes(municipio) ? r.filter((m) => m !== municipio) : [...r, municipio]))
  }

  const editandoAlgo = temRascunho || editando !== null
  const {
    vertices, areaKm2: area, perimetroKm, podeDesfazer, podeRefazer,
    selecionado, selecionadoLiso,
  } = editor.estado

  if (carregandoKey) return <AsyncState loading skeletonRows={3}>{null}</AsyncState>
  if (!key) {
    return <EmptyState icon={<MapPin />} title="Google Maps não configurado"
      description="Defina a chave do Google Maps em Configurações → Geral para desenhar cercas no mapa." />
  }

  return (
    <div className="grid gap-4 lg:grid-cols-[1fr_340px]">
      {/* Mapa */}
      <Card>
        <CardContent className="relative p-0">
          <div ref={mapRef} className="h-[560px] w-full rounded-lg" />
          {erroMapa && <div className="absolute inset-0 grid place-items-center bg-card/80 p-4 text-center text-sm text-destructive">{erroMapa}</div>}
          {podeEditar && pronto && !editandoAlgo && (
            <div className="absolute left-3 top-3 flex gap-2">
              <Button className="shadow-md" onClick={iniciarDesenho}><Plus size={16} /> Desenhar cerca</Button>
              {/* Comeca ja no modo quadra: o primeiro clique fecha o
                  quarteirao em vez de largar um vertice solto. */}
              <Button
                variant="secondary" className="shadow-md"
                onClick={() => { iniciarDesenho(); setModoQuadra(true) }}
                title="Clique dentro de uma quadra e o contorno fecha pelas ruas em volta"
              >
                <Blocks size={16} /> Selecionar quadras
              </Button>
            </div>
          )}

          {/* Barra de ferramentas do editor: só aparece com contorno aberto. */}
          {editandoAlgo && temRascunho && (
            <div className="absolute left-3 top-3 flex gap-1 rounded-md bg-card/95 p-1 shadow-md">
              <Button variant="ghost" size="icon" title="Desfazer (Ctrl+Z)"
                disabled={!podeDesfazer} onClick={() => editor.desfazer()}>
                <Undo2 size={16} />
              </Button>
              <Button variant="ghost" size="icon" title="Refazer (Ctrl+Y)"
                disabled={!podeRefazer} onClick={() => editor.refazer()}>
                <Redo2 size={16} />
              </Button>
              <div className="mx-1 w-px bg-border" />

              {/* Modo quadra: enquanto ligado, cada clique no mapa soma um
                  quarteirao. Fica na barra como interruptor porque o gesto
                  muda o significado do clique, e isso precisa ficar visivel. */}
              <Button
                variant={modoQuadra ? 'default' : 'ghost'} size="sm" className="h-8 text-xs"
                onClick={() => setModoQuadra((v) => !v)}
                loading={quadraMut.isPending}
                title="Clique dentro de uma quadra e ela e somada ao contorno"
              >
                <Blocks size={15} /> Quadra
              </Button>
              <div className="mx-1 w-px bg-border" />

              {/* Ações do ponto selecionado. Ficam na barra e não num card
                  flutuante: o gesto principal (arrastar para moldar, duplo
                  clique para alternar) já acontece no próprio ponto, e um card
                  colado nele cobriria justamente a curva que se quer ver. */}
              {selecionado !== null ? (
                <>
                  <Button variant="ghost" size="sm" className="h-8 text-xs"
                    onClick={() => editor.alternarSelecionado()}
                    title="Alterna entre ponto curvo e canto (ou dê duplo clique nele)">
                    {selecionadoLiso ? <CornerUpRight size={15} /> : <Spline size={15} />}
                    {selecionadoLiso ? 'Virar canto' : 'Virar curva'}
                  </Button>
                  <Button variant="ghost" size="icon" className="text-destructive"
                    disabled={vertices <= 3} onClick={() => editor.removerSelecionado()}
                    title={vertices > 3 ? 'Remover este ponto' : 'Um contorno precisa de ao menos 3 pontos'}>
                    <Trash2 size={15} />
                  </Button>
                </>
              ) : (
                <>
                  <Button variant="ghost" size="icon" title="Arredondar todo o contorno"
                    disabled={vertices < 3} onClick={() => editor.curvarTudo()}>
                    <Spline size={16} />
                  </Button>
                  <Button variant="ghost" size="icon" title="Deixar todo o contorno reto"
                    disabled={vertices < 3} onClick={() => editor.retificarTudo()}>
                    <CornerUpRight size={16} />
                  </Button>
                  <Button variant="ghost" size="icon" title="Simplificar (remove pontos redundantes)"
                    disabled={vertices < 4} onClick={() => editor.simplificarContorno()}>
                    <Minimize2 size={16} />
                  </Button>
                </>
              )}
            </div>
          )}

          {/* Dica do gesto: sem isto ninguém descobre o duplo clique. */}
          {editandoAlgo && vertices >= 2 && (
            <div className="absolute right-3 top-3 rounded-md bg-card/95 px-3 py-1.5 text-[11px] text-muted-foreground shadow-md">
              {modoQuadra
                ? 'Clique dentro de uma quadra para somá-la ao contorno'
                : 'Arraste um ponto para moldar · duplo clique alterna curva e canto'}
            </div>
          )}

          {/* Medidas ao vivo: dimensionar o setor enquanto desenha. */}
          {editandoAlgo && vertices >= 3 && (
            <div className="absolute bottom-3 left-3 rounded-md bg-card/95 px-3 py-1.5 text-xs shadow-md">
              <span className="font-medium tabular-nums">{area.toFixed(2)} km²</span>
              <span className="text-muted-foreground"> · {perimetroKm.toFixed(1)} km de contorno</span>
            </div>
          )}

          {/* Ir para um endereço: quem está começando não tem cerca nem, às
              vezes, endereço da empresa cadastrado. */}
          {pronto && !editandoAlgo && (
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
          <Card><CardContent className="space-y-4 pt-6">
            <div className="flex items-center justify-between">
              <p className="font-medium">{editando ? 'Editar cerca' : 'Nova cerca'}</p>
              <Button variant="ghost" size="icon" onClick={cancelar}><X size={16} /></Button>
            </div>

            {!temRascunho ? (
              <div className="space-y-1 rounded-md bg-secondary/60 p-3 text-xs text-muted-foreground">
                <p className="font-medium text-foreground">Como desenhar</p>
                {modoQuadra ? (
                  <>
                    <p>1. Clique DENTRO de uma quadra — o contorno fecha pelas ruas em volta.</p>
                    <p>2. Clique em outra quadra para somá-la ao setor.</p>
                    <p>3. Desligue “Quadra” na barra para ajustar ponto a ponto.</p>
                    <p className="text-muted-foreground">
                      Quadra sem ruas mapeadas não fecha — nesse trecho, desenhe à mão.
                    </p>
                  </>
                ) : (
                  <>
                    <p>1. Clique no mapa em cada esquina do contorno.</p>
                    <p>2. Arraste os pinos numerados para ajustar.</p>
                    <p>3. Arraste um ponto branco (no meio da linha) para inserir vértice.</p>
                    <p>4. Duplo clique num pino remove aquele vértice.</p>
                    <p className="text-muted-foreground">
                      Ou use “Quadra” na barra para fechar quarteirões pelas ruas.
                    </p>
                  </>
                )}
              </div>
            ) : (
              <div className="space-y-1 rounded-md bg-secondary/60 p-3 text-xs">
                <p className="font-medium text-foreground">
                  {vertices} vértice(s)
                  {vertices < 3 && <span className="text-destructive"> — mínimo de 3</span>}
                </p>
                <p className="text-muted-foreground">
                  Arraste os pinos para ajustar · ponto branco insere · duplo clique remove ·
                  clique no mapa acrescenta ao fim.
                </p>
                <p className="text-muted-foreground">
                  Perto da borda de outra cerca, o ponto gruda nela automaticamente.
                </p>
              </div>
            )}

            <Field label="Nome" required><Input autoFocus value={form.descricao} onChange={(e) => setForm((f) => ({ ...f, descricao: e.target.value }))} placeholder="Ex.: Zona Centro" /></Field>
            <Field label="Município">
              <AsyncSelect
                endpoint="/lookups/cidades" value={form.cidade_id ?? null} valueLabel={form.cidadeLabel ?? null}
                onChange={(id, opt) => setForm((f) => ({ ...f, cidade_id: id, cidadeLabel: opt?.label ?? null }))}
                placeholder="Selecione o município…"
              />
            </Field>
            <Field label="Cor">
              <div className="flex flex-wrap gap-2">
                {CORES.map((c) => (
                  <button key={c} type="button" aria-label={`Cor ${c}`}
                    onClick={() => setForm((f) => ({ ...f, cor: c }))}
                    className={`size-7 rounded-full border-2 transition ${form.cor === c ? 'scale-110 border-foreground' : 'border-transparent'}`}
                    style={{ background: c }} />
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
            <div className="mb-2 flex items-center justify-between gap-2">
              <p className="font-medium">Cercas cadastradas</p>
              {(cercas?.length ?? 0) > 0 && (
                <span className="text-xs text-muted-foreground">
                  {cercas!.length} em {grupos.length} município(s)
                </span>
              )}
            </div>

            {/* Conferencia de sobreposicao. Mede AREA comum, nao vertices: duas
                cercas vizinhas compartilham a divisa de proposito, e contar
                vertice acusaria todo par bem desenhado. Cerca-mae englobando
                setor tambem nao entra — e desenho deliberado. */}
            {!carregandoConflitos && (cercas?.length ?? 0) > 1 && (
              conflitos && conflitos.length > 0 ? (
                <div className="mb-3 rounded-md border border-amber-500/40 bg-amber-500/10 p-2">
                  <button
                    onClick={() => setVerConflitos((v) => !v)}
                    className="flex w-full items-center gap-2 text-left text-xs font-medium"
                  >
                    <AlertTriangle size={14} className="shrink-0 text-amber-600" />
                    <span>
                      {conflitos.length} {conflitos.length > 1 ? 'pares se sobrepoem' : 'par se sobrepoe'}
                    </span>
                    <ChevronRight
                      size={13}
                      className={`ml-auto shrink-0 transition-transform ${verConflitos ? 'rotate-90' : ''}`}
                    />
                  </button>
                  {verConflitos && (
                    <ul className="mt-2 space-y-1.5">
                      {conflitos.map((k) => (
                        <li key={`${k.a}-${k.b}`} className="text-xs">
                          <button
                            className="text-left hover:underline"
                            onClick={() => {
                              const alvo = cercas?.find((c) => c.id === k.a)
                              if (alvo) focarCerca(alvo)
                            }}
                            title="Ver no mapa"
                          >
                            <span className="font-medium">{k.descricao_a}</span>
                            <span className="text-muted-foreground"> x </span>
                            <span className="font-medium">{k.descricao_b}</span>
                            <span className="text-muted-foreground">
                              {' '}— {Math.round(k.fracao * 100)}% de area comum
                            </span>
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              ) : (
                <div className="mb-3 flex items-center gap-2 rounded-md bg-emerald-500/10 px-2 py-1.5 text-xs text-emerald-700 dark:text-emerald-400">
                  <Check size={14} className="shrink-0" />
                  Nenhuma cerca se sobrepoe.
                </div>
              )
            )}
            {(cercas?.length ?? 0) > 6 && (
              <Input
                value={filtro} onChange={(e) => setFiltro(e.target.value)}
                placeholder="Filtrar por nome ou município…" className="mb-3 h-8 text-sm"
              />
            )}
            <AsyncState loading={isLoading} empty={!cercas?.length} emptyIcon={<MapPin />} emptyTitle="Nenhuma cerca"
              emptyDescription={podeEditar ? 'Clique em “Desenhar cerca” para criar a primeira.' : undefined}>
              {/* Agrupado por MUNICÍPIO: a lista plana misturava dois níveis —
                  "Turvo" e "Goioxim" são cidades inteiras, "Setor 01" a "08" são
                  zonas dentro de Guarapuava. */}
              <div className="-mx-2 max-h-[430px] overflow-y-auto">
                {grupos.length === 0 && (
                  <p className="px-2 py-4 text-center text-sm text-muted-foreground">
                    Nenhuma cerca corresponde ao filtro.
                  </p>
                )}
                {grupos.map(([municipio, lista]) => (
                  <section key={municipio}>
                    <button
                      onClick={() => alternarGrupo(municipio)}
                      className="sticky top-0 z-10 flex w-full items-center gap-1.5 bg-card px-2 py-1.5 text-left"
                    >
                      <ChevronRight
                        size={14}
                        className={`shrink-0 text-muted-foreground transition-transform ${recolhidos.includes(municipio) ? '' : 'rotate-90'}`}
                      />
                      <span className="truncate text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {municipio}
                      </span>
                      <span className="ml-auto shrink-0 text-xs text-muted-foreground">{lista.length}</span>
                    </button>
                    {!recolhidos.includes(municipio) && (
                      <div className="divide-y divide-border">
                        {lista.map((c) => (
                          <div
                            key={c.id}
                            className={`flex items-center justify-between gap-2 py-2 pl-6 pr-2 transition-colors ${
                              selecionada === c.id ? 'bg-secondary/70' : ''
                            }`}
                          >
                            <button
                              className="flex min-w-0 items-center gap-2 text-left"
                              onClick={() => focarCerca(c)}
                              title="Ver no mapa"
                            >
                              <span className="size-3 shrink-0 rounded-full" style={{ background: c.cor ?? '#FF6200' }} />
                              <span className="truncate text-sm">{c.descricao}</span>
                              <span className="shrink-0 text-xs text-muted-foreground">{c.pontos?.length ?? 0} pts</span>
                            </button>
                            {podeEditar && (
                              <div className="flex shrink-0">
                                <Button variant="ghost" size="icon" title="Editar" onClick={() => abrirEdicao(c)}><Pencil size={15} /></Button>
                                <Button
                                  variant="ghost" size="icon"
                                  title="Ajustar o contorno as ruas (confira antes de salvar)"
                                  loading={ajusteMut.isPending && ajusteMut.variables === c.id}
                                  onClick={() => aplicarVareta(c)}
                                >
                                  <Wand2 size={15} />
                                </Button>
                                <Button variant="ghost" size="icon" title="Duplicar" onClick={() => duplicar(c)}><Copy size={15} /></Button>
                                <Button variant="ghost" size="icon" title="Excluir" onClick={() => setExcluindo(c)}><Trash2 size={15} /></Button>
                              </div>
                            )}
                          </div>
                        ))}
                      </div>
                    )}
                  </section>
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
