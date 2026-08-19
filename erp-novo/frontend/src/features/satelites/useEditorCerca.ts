import { useCallback, useRef, useState } from 'react'
import {
  areaKm2, meio, metrosEntre, perimetroKm, simplificar, suavizar, type Ponto,
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
 */

const COR_MEIO = '#ffffff'

interface Opcoes {
  /** Vértices das outras cercas, para o snap. */
  vizinhos: Ponto[]
  /** Grudar quando estiver a menos disto (metros). */
  snapMetros?: number
}

export interface EstadoCerca {
  vertices: number
  areaKm2: number
  perimetroKm: number
  podeDesfazer: boolean
  podeRefazer: boolean
}

const VAZIO: EstadoCerca = {
  vertices: 0, areaKm2: 0, perimetroKm: 0, podeDesfazer: false, podeRefazer: false,
}

export function useEditorCerca({ vizinhos, snapMetros = 30 }: Opcoes) {
  const mapa = useRef<any>(null)
  const poligono = useRef<any>(null)
  const marcadores = useRef<any[]>([])
  const meios = useRef<any[]>([])
  const cor = useRef('#FF6200')

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

  const publicar = useCallback(() => {
    const pontos = lerPontos()
    setEstado({
      vertices: pontos.length,
      areaKm2: areaKm2(pontos),
      perimetroKm: perimetroKm(pontos),
      podeDesfazer: passado.current.length > 0,
      podeRefazer: futuro.current.length > 0,
    })
  }, [lerPontos])

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

  // `redesenhar` se auto-referencia nos listeners dos marcadores. Em
  // `useCallback` isso capturaria a versão do render anterior; a ref garante
  // que o listener sempre chame a função atual.
  const redesenharRef = useRef<() => void>(() => {})

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

    for (let i = 0; i < total; i++) {
      const m = new google.maps.Marker({
        position: path.getAt(i),
        map: mapa.current,
        draggable: true,
        crossOnDrag: false,
        label: { text: String(i + 1), color: '#fff', fontSize: '11px', fontWeight: '600' },
        icon: {
          path: google.maps.SymbolPath.CIRCLE, scale: 9,
          fillColor: cor.current, fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2,
        },
        title: `Vértice ${i + 1} — arraste para mover, duplo clique para remover`,
        zIndex: 20,
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
        redesenharRef.current()
        publicar()
      })
      m.addListener('dblclick', () => {
        if (total <= 3) return
        marcarHistorico()
        path.removeAt(i)
        redesenharRef.current()
        publicar()
      })

      marcadores.current.push(m)
    }

    // Pontos-médio: menores e translúcidos, entre cada par. Arrastar um deles
    // INSERE um vértice ali — é como se acrescenta detalhe a um trecho sem
    // refazer o contorno.
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
          title: 'Arraste para inserir um vértice aqui',
          zIndex: 15,
        })

        mm.addListener('dragstart', () => {
          marcarHistorico()
          path.insertAt(i + 1, mm.getPosition())
        })
        mm.addListener('drag', (ev: any) => path.setAt(i + 1, ev.latLng))
        mm.addListener('dragend', () => { redesenharRef.current(); publicar() })

        meios.current.push(mm)
      }
    }
  }, [comSnap, marcarHistorico, publicar])

  redesenharRef.current = redesenhar

  /** Abre um contorno para edição (cerca existente ou rascunho novo). */
  const abrir = useCallback((mapaGoogle: any, pontos: Ponto[], corHex: string) => {
    const google = (window as any).google
    mapa.current = mapaGoogle
    cor.current = corHex
    passado.current = []
    futuro.current = []

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
    redesenhar()
    publicar()
  }, [lerPontos, redesenhar, publicar])

  const refazer = useCallback(() => {
    const proximo = futuro.current.pop()
    if (!proximo) return
    passado.current.push(lerPontos())
    const google = (window as any).google
    poligono.current?.setPath(proximo.map((p) => new google.maps.LatLng(p.lat, p.lng)))
    redesenhar()
    publicar()
  }, [lerPontos, redesenhar, publicar])

  const fechar = useCallback(() => {
    poligono.current?.setMap(null)
    poligono.current = null
    marcadores.current.forEach((m) => m.setMap(null))
    meios.current.forEach((m) => m.setMap(null))
    marcadores.current = []
    meios.current = []
    passado.current = []
    futuro.current = []
    setEstado(VAZIO)
  }, [])

  return {
    estado,
    abrir,
    fechar,
    acrescentar,
    desfazer,
    refazer,
    lerPontos,
    /** Suaviza os cantos densificando o contorno em vértices. */
    suavizarContorno: () => aplicar(suavizar(lerPontos())),
    /** Remove pontos que quase não mudam a linha. */
    simplificarContorno: () => aplicar(simplificar(lerPontos())),
    /** Há polígono aberto para edição? */
    get ativo() { return poligono.current !== null },
  }
}
