import { useCallback, useRef, useState } from 'react'
import {
  areaKm2, cascoConvexo, meio, metrosEntre, perimetroKm, simplificar, tracado,
  type No, type Ponto,
} from './editorPoligono'

/**
 * Edição do contorno de uma cerca no Google Maps, no modelo da ferramenta
 * Curvatura do Illustrator.
 *
 * **Cada nó guarda se é liso ou canto.** Arrastar um nó liso arqueia a linha ao
 * vivo; duplo clique alterna entre curva e bico. A curva não é gravada: o banco
 * guarda os nós e o traçado é recalculado a cada desenho. É isso que permite
 * mexer no ponto depois de curvar — na versão anterior a curva era densificada
 * em vértices e o ponto original sumia, então não havia mais o que arrastar.
 *
 * **Uma camada só de interação.** O polígono nunca é `editable`; todo gesto
 * passa pelos marcadores. Duas camadas sobrepostas disputando o clique foi o
 * que travou os vértices numa tentativa anterior.
 */

const COR_MEIO = '#ffffff'
/** Cor do nó selecionado — precisa destoar de qualquer cor de cerca. */
const COR_SELECIONADO = '#111827'

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
  /** Índice do nó selecionado, ou null. */
  selecionado: number | null
  /** O nó selecionado é liso? (para rotular o botão de alternar) */
  selecionadoLiso: boolean
}

const VAZIO: EstadoCerca = {
  vertices: 0,
  areaKm2: 0,
  perimetroKm: 0,
  podeDesfazer: false,
  podeRefazer: false,
  selecionado: null,
  selecionadoLiso: false,
}

export function useEditorCerca({ vizinhos, snapMetros = 30 }: Opcoes) {
  const mapa = useRef<any>(null)
  const poligono = useRef<any>(null)
  const marcadores = useRef<any[]>([])
  const meios = useRef<any[]>([])
  const cor = useRef('#FF6200')
  const selecionado = useRef<number | null>(null)

  /**
   * Os nós são a fonte da verdade — não o path do polígono.
   *
   * O path desenhado é derivado (curva aproximada em segmentos), então ler dele
   * de volta perderia a informação de qual ponto é liso e devolveria dezenas de
   * pontos onde há um nó só.
   */
  const nos = useRef<No[]>([])

  // Histórico como pilhas de snapshots. Um contorno tem dezenas de nós e a
  // edição é curta: guardar a lista inteira é mais simples (e mais seguro) que
  // reverter operação por operação.
  const passado = useRef<No[][]>([])
  const futuro = useRef<No[][]>([])

  const [estado, setEstado] = useState<EstadoCerca>(VAZIO)

  // Os listeners dos marcadores são criados a cada `redesenhar`; ler os
  // vizinhos de uma ref evita que o snap fique preso à lista de quando o
  // contorno foi aberto.
  const vizinhosRef = useRef(vizinhos)
  vizinhosRef.current = vizinhos

  /** Nós atuais (cópia — quem chama não deve alterar o interno por engano). */
  const lerNos = useCallback((): No[] => nos.current.map((p) => ({ ...p })), [])

  /**
   * O contorno como lista de pontos, já com as curvas resolvidas.
   *
   * É o que vai para o banco: o geofencing e o rastreador continuam recebendo
   * uma lista de coordenadas, sem saber que houve curva.
   */
  const lerPontos = useCallback((): Ponto[] => tracado(nos.current), [])

  const publicar = useCallback(() => {
    const pontos = lerPontos()
    const i = selecionado.current
    setEstado({
      vertices: nos.current.length,
      areaKm2: areaKm2(pontos),
      perimetroKm: perimetroKm(pontos),
      podeDesfazer: passado.current.length > 0,
      podeRefazer: futuro.current.length > 0,
      selecionado: i,
      selecionadoLiso: i !== null ? (nos.current[i]?.liso ?? false) : false,
    })
  }, [lerPontos])

  /** Guarda o traçado atual antes de uma alteração — é o que o desfazer usa. */
  const marcarHistorico = useCallback(() => {
    passado.current.push(lerNos())
    // 50 passos bastam para qualquer edição e evitam segurar memória à toa.
    if (passado.current.length > 50) passado.current.shift()
    futuro.current = []
  }, [lerNos])

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

  /** Repinta o traçado a partir dos nós, sem refazer os marcadores. */
  const repintarTracado = useCallback(() => {
    const google = (window as any).google
    if (!poligono.current) return
    poligono.current.setPath(
      lerPontos().map((p) => new google.maps.LatLng(p.lat, p.lng)),
    )
  }, [lerPontos])

  /** Redesenha marcadores dos nós e dos pontos-médio. */
  const redesenhar = useCallback(() => {
    const google = (window as any).google
    marcadores.current.forEach((m) => m.setMap(null))
    meios.current.forEach((m) => m.setMap(null))
    marcadores.current = []
    meios.current = []
    if (!poligono.current || !mapa.current) return

    repintarTracado()

    const lista = nos.current
    const total = lista.length

    // Um nó pode ter sumido (remoção, simplificação): a seleção presa a um
    // índice que não existe mais deixaria a barra agindo sobre o vazio.
    if (selecionado.current !== null && selecionado.current >= total) {
      selecionado.current = null
    }

    lista.forEach((no, i) => {
      const ativo = selecionado.current === i
      const m = new google.maps.Marker({
        position: { lat: no.lat, lng: no.lng },
        map: mapa.current,
        draggable: true,
        crossOnDrag: false,
        // Nó liso é círculo, nó de canto é quadrado — a mesma convenção visual
        // do Illustrator, legível sem precisar clicar para descobrir.
        icon: {
          path: no.liso
            ? google.maps.SymbolPath.CIRCLE
            : 'M -7,-7 L 7,-7 L 7,7 L -7,7 Z',
          scale: no.liso ? (ativo ? 9 : 7) : (ativo ? 1.1 : 0.85),
          fillColor: ativo ? COR_SELECIONADO : cor.current,
          fillOpacity: 1,
          strokeColor: '#fff',
          strokeWeight: ativo ? 3 : 2,
        },
        title: no.liso
          ? 'Ponto curvo — arraste para moldar, duplo clique vira canto'
          : 'Canto — arraste para mover, duplo clique vira curva',
        zIndex: ativo ? 30 : 20,
      })

      m.addListener('click', () => {
        selecionado.current = selecionado.current === i ? null : i
        redesenharRef.current()
        publicarRef.current()
      })

      m.addListener('dragstart', () => marcarHistorico())
      // O traçado acompanha o arrasto: é o que dá a sensação de moldar a curva
      // com o dedo, em vez de aplicar um comando e ver o resultado depois.
      m.addListener('drag', (ev: any) => {
        nos.current[i] = { ...nos.current[i], lat: ev.latLng.lat(), lng: ev.latLng.lng() }
        repintarTracado()
      })
      m.addListener('dragend', (ev: any) => {
        const grudado = comSnap({ lat: ev.latLng.lat(), lng: ev.latLng.lng() })
        nos.current[i] = { ...nos.current[i], ...grudado }
        // Arrastar também seleciona: quem mexeu no ponto quase sempre quer
        // ajustá-lo em seguida.
        selecionado.current = i
        redesenharRef.current()
        publicarRef.current()
      })

      // O gesto central da ferramenta Curvatura: alterna curva × canto.
      m.addListener('dblclick', () => {
        marcarHistorico()
        nos.current[i] = { ...nos.current[i], liso: !nos.current[i].liso }
        selecionado.current = i
        redesenharRef.current()
        publicarRef.current()
      })

      marcadores.current.push(m)
    })

    // Pontos-médio: menores e translúcidos, entre cada par de nós. Clicar ou
    // arrastar um deles INSERE um nó ali — é como se acrescenta detalhe a um
    // trecho sem refazer o contorno. Nasce liso, porque quem insere ponto no
    // meio de uma linha quase sempre quer moldá-la.
    if (total >= 2) {
      for (let i = 0; i < total; i++) {
        const a = lista[i]
        const b = lista[(i + 1) % total]
        const centro = meio(a, b)

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
          title: 'Clique ou arraste para inserir um ponto aqui',
          zIndex: 15,
        })

        const inserir = (p: Ponto) => {
          nos.current.splice(i + 1, 0, { ...p, liso: true })
        }

        mm.addListener('click', () => {
          marcarHistorico()
          inserir(centro)
          selecionado.current = i + 1
          redesenharRef.current()
          publicarRef.current()
        })
        mm.addListener('dragstart', () => {
          marcarHistorico()
          inserir(centro)
        })
        mm.addListener('drag', (ev: any) => {
          nos.current[i + 1] = {
            ...nos.current[i + 1], lat: ev.latLng.lat(), lng: ev.latLng.lng(),
          }
          repintarTracado()
        })
        mm.addListener('dragend', () => {
          selecionado.current = i + 1
          redesenharRef.current()
          publicarRef.current()
        })

        meios.current.push(mm)
      }
    }
  }, [comSnap, marcarHistorico, repintarTracado])

  redesenharRef.current = redesenhar

  /**
   * Abre um contorno para edição.
   *
   * Cerca vinda do banco chega como lista de coordenadas, sem informação de
   * curvatura: todos os nós entram como canto. É o comportamento certo — o
   * traçado gravado é exatamente o que se vê, e nada muda de forma sozinho ao
   * abrir uma cerca antiga.
   */
  const abrir = useCallback((mapaGoogle: any, pontos: Ponto[], corHex: string) => {
    const google = (window as any).google
    mapa.current = mapaGoogle
    cor.current = corHex
    passado.current = []
    futuro.current = []
    selecionado.current = null
    nos.current = pontos.map((p) => ({ lat: p.lat, lng: p.lng, liso: false }))

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

  /** Acrescenta um nó no fim (usado no desenho por cliques no mapa). */
  const acrescentar = useCallback((p: Ponto) => {
    if (!poligono.current) return
    marcarHistorico()
    nos.current.push({ ...comSnap(p), liso: false })
    redesenhar()
    publicar()
  }, [comSnap, marcarHistorico, redesenhar, publicar])

  const aplicar = useCallback((novos: No[]) => {
    if (!poligono.current) return
    marcarHistorico()
    nos.current = novos
    redesenhar()
    publicar()
  }, [marcarHistorico, redesenhar, publicar])

  const desfazer = useCallback(() => {
    const anterior = passado.current.pop()
    if (!anterior) return
    futuro.current.push(lerNos())
    nos.current = anterior
    selecionado.current = null
    redesenhar()
    publicar()
  }, [lerNos, redesenhar, publicar])

  const refazer = useCallback(() => {
    const proximo = futuro.current.pop()
    if (!proximo) return
    passado.current.push(lerNos())
    nos.current = proximo
    selecionado.current = null
    redesenhar()
    publicar()
  }, [lerNos, redesenhar, publicar])

  const fechar = useCallback(() => {
    poligono.current?.setMap(null)
    poligono.current = null
    marcadores.current.forEach((m) => m.setMap(null))
    meios.current.forEach((m) => m.setMap(null))
    marcadores.current = []
    meios.current = []
    passado.current = []
    futuro.current = []
    nos.current = []
    selecionado.current = null
    setEstado(VAZIO)
  }, [])

  /** Alterna curva × canto no nó selecionado (o mesmo que o duplo clique). */
  const alternarSelecionado = useCallback(() => {
    const i = selecionado.current
    if (i === null || !nos.current[i]) return
    const novos = lerNos()
    novos[i].liso = !novos[i].liso
    aplicar(novos)
  }, [lerNos, aplicar])

  /** Remove o nó selecionado. */
  const removerSelecionado = useCallback(() => {
    const i = selecionado.current
    if (i === null || nos.current.length <= 3) return
    selecionado.current = null
    aplicar(lerNos().filter((_, k) => k !== i))
  }, [lerNos, aplicar])

  /** Torna todos os nós curvos — o contorno inteiro fica arredondado. */
  const curvarTudo = useCallback(() => {
    aplicar(lerNos().map((p) => ({ ...p, liso: true })))
  }, [lerNos, aplicar])

  /** Volta todos os nós a canto — desfaz o arredondamento sem perder pontos. */
  const retificarTudo = useCallback(() => {
    aplicar(lerNos().map((p) => ({ ...p, liso: false })))
  }, [lerNos, aplicar])

  /**
   * Substitui o contorno inteiro por outro — as ferramentas assistidas.
   *
   * Entra no historico como UM passo: Ctrl+Z devolve o contorno anterior
   * inteiro, que e o que se espera de "apliquei a sugestao e nao gostei".
   *
   * Os nos entram como CANTO porque o contorno sugerido ja chega denso e com a
   * forma final — quadra fechada pelas ruas, ou traçado encaixado pela Roads
   * API. Marca-los como lisos arquearia por cima de uma geometria que ja esta
   * certa, justamente o defeito que a ferramenta veio corrigir.
   */
  const substituir = useCallback((pontos: Ponto[]) => {
    if (pontos.length < 3) return
    aplicar(pontos.map((p) => ({ lat: p.lat, lng: p.lng, liso: false })))
  }, [aplicar])

  /**
   * Soma um contorno ao que ja existe — montar setor clicando quadra a quadra.
   *
   * A uniao e feita pelo CASCO CONVEXO das duas areas. E aproximacao: um setor
   * em L fica com o canto preenchido. Escolhida mesmo assim porque a alternativa
   * seria trazer um clipper de poligonos inteiro para o navegador, e o operador
   * tem os pontos na mao para corrigir o canto — enquanto uniao errada de
   * geometria ele nao teria como consertar.
   */
  const unir = useCallback((pontos: Ponto[]) => {
    if (pontos.length < 3) return
    if (nos.current.length < 3) { substituir(pontos); return }
    const juntos = [...lerNos().map(({ lat, lng }) => ({ lat, lng })), ...pontos]
    aplicar(cascoConvexo(juntos).map((p) => ({ ...p, liso: false })))
  }, [aplicar, lerNos, substituir])

  /**
   * Remove nós que quase não mudam a linha.
   *
   * Só considera a posição: um nó liso removido leva junto a sua curvatura, o
   * que é o esperado — ele deixou de existir.
   */
  const simplificarContorno = useCallback(() => {
    const antes = lerNos()
    const mantidos = simplificar(antes.map(({ lat, lng }) => ({ lat, lng })))
    const chave = (p: Ponto) => `${p.lat},${p.lng}`
    const manter = new Set(mantidos.map(chave))
    aplicar(antes.filter((p) => manter.has(chave(p))))
  }, [lerNos, aplicar])

  return {
    estado,
    abrir,
    fechar,
    acrescentar,
    desfazer,
    refazer,
    lerPontos,
    lerNos,
    alternarSelecionado,
    removerSelecionado,
    curvarTudo,
    retificarTudo,
    simplificarContorno,
    substituir,
    unir,
    /** Há contorno aberto para edição? */
    get ativo() { return poligono.current !== null },
  }
}
