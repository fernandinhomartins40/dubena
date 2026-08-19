import { useCallback, useRef, useState } from 'react'
import {
  arredondarCanto, areaKm2, meio, metrosEntre, perimetroKm, simplificar, suavizar, type Ponto,
} from './editorPoligono'

/**
 * Edição do contorno de uma cerca no Google Maps.
 *
 * Concentra os gestos e o histórico; o componente de tela só chama os comandos
 * e desenha os botões. A separação existe porque a versão anterior misturava
 * refs do mapa, estado do React e JSX no mesmo arquivo — e cada ajuste na
 * interação quebrava o desenho.
 *
 * **Regra que orienta tudo aqui: uma camada só de interação.** O polígono nunca
 * é `editable`; todo gesto passa pelos marcadores. Duas camadas sobrepostas
 * disputando o clique foi o que travou os vértices na tentativa anterior.
 *
 * **Seleção por vértice.** Clicar num pino o seleciona e abre o card de
 * ferramentas ao lado dele, como o painel de ponto do Illustrator. Curvatura,
 * remoção e alinhamento agem sobre o ponto selecionado, não sobre o contorno
 * inteiro — arredondar uma esquina não pode deixar o quarteirão todo redondo.
 */

const COR_MEIO = '#ffffff'
/** Cor do pino selecionado — precisa destoar de qualquer cor de cerca. */
const COR_SELECIONADO = '#111827'

interface Opcoes {
  /** Vértices das outras cercas, para o snap. */
  vizinhos: Ponto[]
  /** Grudar quando estiver a menos disto (metros). */
  snapMetros?: number
}

/** Onde o card de ferramentas deve aparecer, em pixels do container do mapa. */
export interface PosicaoTela { x: number; y: number }

export interface EstadoCerca {
  vertices: number
  areaKm2: number
  perimetroKm: number
  podeDesfazer: boolean
  podeRefazer: boolean
  /** Índice do vértice selecionado, ou null. */
  selecionado: number | null
  /** Posição em tela do vértice selecionado (para ancorar o card). */
  posicaoSelecionado: PosicaoTela | null
}

const VAZIO: EstadoCerca = {
  vertices: 0,
  areaKm2: 0,
  perimetroKm: 0,
  podeDesfazer: false,
  podeRefazer: false,
  selecionado: null,
  posicaoSelecionado: null,
}

export function useEditorCerca({ vizinhos, snapMetros = 30 }: Opcoes) {
  const mapa = useRef<any>(null)
  const poligono = useRef<any>(null)
  const marcadores = useRef<any[]>([])
  const meios = useRef<any[]>([])
  const cor = useRef('#FF6200')
  const selecionado = useRef<number | null>(null)
  /** Listeners de movimentação do mapa — reposicionam o card ao arrastar/zoom. */
  const listenerMapa = useRef<any[]>([])

  // Histórico como pilhas de snapshots. Um polígono tem dezenas de pontos e a
  // edição é curta: guardar a lista inteira é mais simples (e mais seguro) que
  // reverter operação por operação.
  const passado = useRef<Ponto[][]>([])
  const futuro = useRef<Ponto[][]>([])

  const [estado, setEstado] = useState<EstadoCerca>(VAZIO)

  // Os listeners dos marcadores são criados a cada `redesenhar`; ler os
  // vizinhos de uma ref evita que o snap fique preso à lista de quando o
  // contorno foi aberto.
  const vizinhosRef = useRef(vizinhos)
  vizinhosRef.current = vizinhos

  /** Lê o contorno atual do polígono. */
  const lerPontos = useCallback((): Ponto[] => {
    if (!poligono.current) return []
    const saida: Ponto[] = []
    poligono.current.getPath().forEach((p: any) => saida.push({ lat: p.lat(), lng: p.lng() }))

    return saida
  }, [])

  /**
   * Converte a posição do vértice selecionado em pixels do container do mapa.
   *
   * O card flutuante é HTML sobreposto, então precisa da projeção: não há como
   * posicioná-lo por latitude/longitude.
   */
  const posicaoDoSelecionado = useCallback((): PosicaoTela | null => {
    const i = selecionado.current
    if (i === null || !mapa.current || !poligono.current) return null

    const path = poligono.current.getPath()
    if (i >= path.getLength()) return null

    const projecao = mapa.current.getProjection?.()
    const limites = mapa.current.getBounds?.()
    if (!projecao || !limites) return null

    const ne = projecao.fromLatLngToPoint(limites.getNorthEast())
    const sw = projecao.fromLatLngToPoint(limites.getSouthWest())
    const ponto = projecao.fromLatLngToPoint(path.getAt(i))
    if (!ne || !sw || !ponto) return null

    const escala = 2 ** mapa.current.getZoom()

    return {
      x: Math.round((ponto.x - sw.x) * escala),
      y: Math.round((ponto.y - ne.y) * escala),
    }
  }, [])

  const publicar = useCallback(() => {
    const pontos = lerPontos()
    setEstado({
      vertices: pontos.length,
      areaKm2: areaKm2(pontos),
      perimetroKm: perimetroKm(pontos),
      podeDesfazer: passado.current.length > 0,
      podeRefazer: futuro.current.length > 0,
      selecionado: selecionado.current,
      posicaoSelecionado: posicaoDoSelecionado(),
    })
  }, [lerPontos, posicaoDoSelecionado])

  /** Guarda o traçado atual antes de uma alteração — é o que o desfazer usa. */
  const marcarHistorico = useCallback(() => {
    passado.current.push(lerPontos())
    // 50 passos bastam para qualquer edição e evitam segurar memória à toa.
    if (passado.current.length > 50) passado.current.shift()
    futuro.current = []
  }, [lerPontos])

  /**
   * Gruda o ponto num vértice de cerca vizinha, quando houver um perto.
   *
   * Sem isto, setores adjacentes ficam com buracos ou sobreposição entre eles —
   * e o geofencing passa a ter endereços em nenhuma cerca ou em duas.
   */
  const comSnap = useCallback((p: Ponto): Ponto => {
    let melhor: Ponto | null = null
    let menor = snapMetros

    for (const v of vizinhosRef.current) {
      const d = metrosEntre(p, v)
      if (d < menor) { menor = d; melhor = v }
    }

    return melhor ?? p
  }, [snapMetros])

  // `redesenhar` e `publicar` se auto-referenciam nos listeners dos marcadores.
  // Em `useCallback` isso capturaria a versão do render anterior; as refs
  // garantem que o listener sempre chame a função atual.
  const redesenharRef = useRef<() => void>(() => {})
  const publicarRef = useRef<() => void>(() => {})
  publicarRef.current = publicar

  /** Redesenha marcadores dos vértices e dos pontos-médio. */
  const redesenhar = useCallback(() => {
    const google = (window as any).google
    marcadores.current.forEach((m) => m.setMap(null))
    meios.current.forEach((m) => m.setMap(null))
    marcadores.current = []
    meios.current = []
    if (!poligono.current || !mapa.current) return

    const path = poligono.current.getPath()
    const total = path.getLength()

    // Um vértice pode ter sumido (remoção, simplificação): a seleção presa a um
    // índice que não existe mais deixaria o card apontando para o vazio.
    if (selecionado.current !== null && selecionado.current >= total) {
      selecionado.current = null
    }

    for (let i = 0; i < total; i++) {
      const ativo = selecionado.current === i
      const m = new google.maps.Marker({
        position: path.getAt(i),
        map: mapa.current,
        draggable: true,
        crossOnDrag: false,
        label: { text: String(i + 1), color: '#fff', fontSize: '11px', fontWeight: '600' },
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          scale: ativo ? 12 : 9,
          fillColor: ativo ? COR_SELECIONADO : cor.current,
          fillOpacity: 1,
          strokeColor: '#fff',
          strokeWeight: ativo ? 3 : 2,
        },
        title: `Vértice ${i + 1} — clique para as ferramentas, arraste para mover`,
        zIndex: ativo ? 30 : 20,
      })

      // Clique seleciona: é o que abre o card de ferramentas daquele ponto.
      m.addListener('click', () => {
        selecionado.current = selecionado.current === i ? null : i
        redesenharRef.current()
        publicarRef.current()
      })

      m.addListener('dragstart', () => marcarHistorico())
      m.addListener('drag', (ev: any) => {
        path.setAt(i, ev.latLng)
        // Os pontos-médio acompanham o arrasto: redesenhá-los durante o gesto
        // deixaria o marcador sendo arrastado sob o dedo, então só o contorno
        // se move aqui — os médios são refeitos ao soltar.
      })
      m.addListener('dragend', (ev: any) => {
        const grudado = comSnap({ lat: ev.latLng.lat(), lng: ev.latLng.lng() })
        path.setAt(i, new google.maps.LatLng(grudado.lat, grudado.lng))
        // Arrastar também seleciona: quem mexeu no ponto quase sempre quer
        // ajustá-lo em seguida.
        selecionado.current = i
        redesenharRef.current()
        publicarRef.current()
      })
      m.addListener('dblclick', () => {
        if (total <= 3) return
        marcarHistorico()
        path.removeAt(i)
        selecionado.current = null
        redesenharRef.current()
        publicarRef.current()
      })

      marcadores.current.push(m)
    }

    // Pontos-médio: menores e translúcidos, entre cada par. Clicar ou arrastar
    // um deles INSERE um vértice ali — é como se acrescenta detalhe a um trecho
    // sem refazer o contorno.
    if (total >= 2) {
      for (let i = 0; i < total; i++) {
        const a = path.getAt(i)
        const b = path.getAt((i + 1) % total)
        const centro = meio({ lat: a.lat(), lng: a.lng() }, { lat: b.lat(), lng: b.lng() })

        const mm = new google.maps.Marker({
          position: centro,
          map: mapa.current,
          draggable: true,
          crossOnDrag: false,
          icon: {
            path: google.maps.SymbolPath.CIRCLE, scale: 5,
            fillColor: COR_MEIO, fillOpacity: 0.9,
            strokeColor: cor.current, strokeWeight: 2,
          },
          title: 'Clique ou arraste para inserir um vértice aqui',
          zIndex: 15,
        })

        mm.addListener('click', () => {
          marcarHistorico()
          path.insertAt(i + 1, mm.getPosition())
          selecionado.current = i + 1
          redesenharRef.current()
          publicarRef.current()
        })
        mm.addListener('dragstart', () => {
          marcarHistorico()
          path.insertAt(i + 1, mm.getPosition())
        })
        mm.addListener('drag', (ev: any) => path.setAt(i + 1, ev.latLng))
        mm.addListener('dragend', () => {
          selecionado.current = i + 1
          redesenharRef.current()
          publicarRef.current()
        })

        meios.current.push(mm)
      }
    }
  }, [comSnap, marcarHistorico])

  redesenharRef.current = redesenhar

  /** Abre um contorno para edição (cerca existente ou rascunho novo). */
  const abrir = useCallback((mapaGoogle: any, pontos: Ponto[], corHex: string) => {
    const google = (window as any).google
    mapa.current = mapaGoogle
    cor.current = corHex
    passado.current = []
    futuro.current = []
    selecionado.current = null

    poligono.current?.setMap(null)
    poligono.current = new google.maps.Polygon({
      paths: pontos,
      fillColor: corHex, fillOpacity: 0.3,
      strokeColor: corHex, strokeWeight: 2,
      // NUNCA editable: os marcadores são a única camada de interação.
      editable: false,
      map: mapaGoogle,
      zIndex: 5,
    })

    // O card é HTML posicionado em pixels: mover ou dar zoom no mapa muda onde
    // o vértice está na tela, e sem isto ele ficaria para trás.
    listenerMapa.current.forEach((l) => google.maps.event.removeListener(l))
    listenerMapa.current = ['bounds_changed', 'zoom_changed'].map((evento) =>
      google.maps.event.addListener(mapaGoogle, evento, () => {
        if (selecionado.current !== null) publicarRef.current()
      }),
    )

    redesenhar()
    publicar()
  }, [redesenhar, publicar])

  /** Acrescenta um vértice no fim (usado no desenho por cliques no mapa). */
  const acrescentar = useCallback((p: Ponto) => {
    const google = (window as any).google
    if (!poligono.current) return
    marcarHistorico()
    const grudado = comSnap(p)
    poligono.current.getPath().push(new google.maps.LatLng(grudado.lat, grudado.lng))
    redesenhar()
    publicar()
  }, [comSnap, marcarHistorico, redesenhar, publicar])

  const aplicar = useCallback((novos: Ponto[]) => {
    const google = (window as any).google
    if (!poligono.current) return
    marcarHistorico()
    poligono.current.setPath(novos.map((p) => new google.maps.LatLng(p.lat, p.lng)))
    redesenhar()
    publicar()
  }, [marcarHistorico, redesenhar, publicar])

  const desfazer = useCallback(() => {
    const anterior = passado.current.pop()
    if (!anterior) return
    futuro.current.push(lerPontos())
    const google = (window as any).google
    poligono.current?.setPath(anterior.map((p) => new google.maps.LatLng(p.lat, p.lng)))
    selecionado.current = null
    redesenhar()
    publicar()
  }, [lerPontos, redesenhar, publicar])

  const refazer = useCallback(() => {
    const proximo = futuro.current.pop()
    if (!proximo) return
    passado.current.push(lerPontos())
    const google = (window as any).google
    poligono.current?.setPath(proximo.map((p) => new google.maps.LatLng(p.lat, p.lng)))
    selecionado.current = null
    redesenhar()
    publicar()
  }, [lerPontos, redesenhar, publicar])

  const fechar = useCallback(() => {
    const google = (window as any).google
    listenerMapa.current.forEach((l) => google?.maps?.event?.removeListener(l))
    listenerMapa.current = []
    poligono.current?.setMap(null)
    poligono.current = null
    marcadores.current.forEach((m) => m.setMap(null))
    meios.current.forEach((m) => m.setMap(null))
    marcadores.current = []
    meios.current = []
    passado.current = []
    futuro.current = []
    selecionado.current = null
    setEstado(VAZIO)
  }, [])

  /** Tira a seleção — fecha o card sem alterar o traçado. */
  const limparSelecao = useCallback(() => {
    if (selecionado.current === null) return
    selecionado.current = null
    redesenhar()
    publicar()
  }, [redesenhar, publicar])

  /**
   * Arredonda o canto do vértice selecionado.
   *
   * A curva vira vértices e o ponto original some, então a seleção não faz mais
   * sentido depois: o card fecha, e quem quiser mais curva seleciona um dos
   * pontos novos.
   */
  const curvarSelecionado = useCallback((intensidade: number) => {
    const i = selecionado.current
    if (i === null) return
    const novos = arredondarCanto(lerPontos(), i, intensidade)
    selecionado.current = null
    aplicar(novos)
  }, [lerPontos, aplicar])

  /** Remove o vértice selecionado (o mesmo que o duplo clique no pino). */
  const removerSelecionado = useCallback(() => {
    const i = selecionado.current
    const pontos = lerPontos()
    if (i === null || pontos.length <= 3) return
    selecionado.current = null
    aplicar(pontos.filter((_, k) => k !== i))
  }, [lerPontos, aplicar])

  /**
   * Alinha o vértice selecionado com os dois vizinhos, tirando o bico.
   *
   * Serve para endireitar um trecho que deveria ser reto — uma avenida, o limite
   * de um quarteirão — sem ter que apagar e redesenhar.
   */
  const alinharSelecionado = useCallback(() => {
    const i = selecionado.current
    const pontos = lerPontos()
    if (i === null || pontos.length < 3) return

    const n = pontos.length
    const a = pontos[(i - 1 + n) % n]
    const b = pontos[(i + 1) % n]
    const novos = [...pontos]
    novos[i] = { lat: (a.lat + b.lat) / 2, lng: (a.lng + b.lng) / 2 }
    aplicar(novos)
  }, [lerPontos, aplicar])

  return {
    estado,
    abrir,
    fechar,
    acrescentar,
    desfazer,
    refazer,
    lerPontos,
    limparSelecao,
    curvarSelecionado,
    removerSelecionado,
    alinharSelecionado,
    /** Suaviza TODOS os cantos densificando o contorno em vértices. */
    suavizarContorno: () => aplicar(suavizar(lerPontos())),
    /** Remove pontos que quase não mudam a linha. */
    simplificarContorno: () => aplicar(simplificar(lerPontos())),
    /** Há polígono aberto para edição? */
    get ativo() { return poligono.current !== null },
  }
}
