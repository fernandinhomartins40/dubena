/**
 * Editor de polígono sobre o Google Maps.
 *
 * Isolado do componente de tela porque é lógica de manipulação geométrica —
 * arrastar vértice, inserir no meio de um segmento, suavizar canto, medir área.
 * Misturar isso com JSX deixava `CercasTab` ilegível e impossível de ajustar
 * sem quebrar o resto.
 *
 * **Uma camada só de interação.** A tentativa anterior sobrepunha marcadores ao
 * polígono `editable` do Google: as duas camadas disputavam o clique e os
 * vértices travavam. Aqui o polígono NÃO é editável e todos os gestos passam
 * pelos marcadores — arrastar move, duplo clique remove, e os pontos-médio
 * (menores, entre dois vértices) inserem quando arrastados.
 */

export interface Ponto { lat: number; lng: number }

/** Raio médio da Terra, em metros — usado nas medidas. */
const RAIO_TERRA = 6_371_008.8

export interface OpcoesEditor {
  google: any
  mapa: any
  cor: string
  /** Vértices de outras cercas, para o snap. */
  vizinhos?: Ponto[]
  /** Distância (px) para o snap grudar num vértice vizinho. */
  snapPx?: number
  aoMudar: (estado: EstadoEditor) => void
}

export interface EstadoEditor {
  vertices: number
  /** Área em km². */
  area: number
  /** Perímetro em km. */
  perimetro: number
  podeDesfazer: boolean
  podeRefazer: boolean
}

/**
 * Distância aproximada em metros entre dois pontos (fórmula de haversine).
 *
 * Aproximação boa o bastante para o snap e para o perímetro de um setor urbano;
 * não se pretende precisão geodésica.
 */
export function metrosEntre(a: Ponto, b: Ponto): number {
  const rad = Math.PI / 180
  const dLat = (b.lat - a.lat) * rad
  const dLng = (b.lng - a.lng) * rad
  const h =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(a.lat * rad) * Math.cos(b.lat * rad) * Math.sin(dLng / 2) ** 2

  return 2 * RAIO_TERRA * Math.asin(Math.sqrt(h))
}

/**
 * Suaviza o contorno gerando pontos intermediários (Catmull-Rom → Bézier).
 *
 * **A curva é DENSIFICADA em vértices**, não guardada como curva: o polígono
 * resultante é uma lista de pontos como qualquer outra, então o geofencing
 * atual e o rastreador continuam funcionando sem nenhuma mudança. O custo é ter
 * mais linhas no banco — aceitável para uma cerca que se desenha uma vez.
 *
 * Catmull-Rom porque ela PASSA pelos pontos originais (ao contrário de uma
 * Bézier comum, que só é atraída por eles): o usuário marcou aquela esquina, a
 * curva tem de encostar nela.
 *
 * @param passos segmentos gerados entre cada par de vértices
 */
export function suavizar(pontos: Ponto[], passos = 6): Ponto[] {
  if (pontos.length < 3) return pontos

  const n = pontos.length
  const saida: Ponto[] = []

  for (let i = 0; i < n; i++) {
    // O polígono é fechado: os vizinhos dão a volta no fim da lista.
    const p0 = pontos[(i - 1 + n) % n]
    const p1 = pontos[i]
    const p2 = pontos[(i + 1) % n]
    const p3 = pontos[(i + 2) % n]

    for (let t = 0; t < passos; t++) {
      const s = t / passos
      const s2 = s * s
      const s3 = s2 * s

      saida.push({
        lat: 0.5 * ((2 * p1.lat) + (-p0.lat + p2.lat) * s
          + (2 * p0.lat - 5 * p1.lat + 4 * p2.lat - p3.lat) * s2
          + (-p0.lat + 3 * p1.lat - 3 * p2.lat + p3.lat) * s3),
        lng: 0.5 * ((2 * p1.lng) + (-p0.lng + p2.lng) * s
          + (2 * p0.lng - 5 * p1.lng + 4 * p2.lng - p3.lng) * s2
          + (-p0.lng + 3 * p1.lng - 3 * p2.lng + p3.lng) * s3),
      })
    }
  }

  return saida
}

/**
 * Simplifica o contorno removendo pontos que quase não mudam a linha
 * (Ramer–Douglas–Peucker).
 *
 * Serve de contrapeso à suavização: quem exagerou nos cliques consegue limpar o
 * traçado sem redesenhar. `tolerancia` é em metros.
 */
export function simplificar(pontos: Ponto[], tolerancia = 15): Ponto[] {
  if (pontos.length <= 3) return pontos

  const distanciaAteReta = (p: Ponto, a: Ponto, b: Ponto): number => {
    const dx = b.lng - a.lng
    const dy = b.lat - a.lat
    if (dx === 0 && dy === 0) return metrosEntre(p, a)

    const t = ((p.lng - a.lng) * dx + (p.lat - a.lat) * dy) / (dx * dx + dy * dy)
    const proj = t < 0 ? a : t > 1 ? b : { lat: a.lat + t * dy, lng: a.lng + t * dx }

    return metrosEntre(p, proj)
  }

  const rdp = (lista: Ponto[]): Ponto[] => {
    if (lista.length < 3) return lista

    let pior = 0
    let indice = 0
    for (let i = 1; i < lista.length - 1; i++) {
      const d = distanciaAteReta(lista[i], lista[0], lista[lista.length - 1])
      if (d > pior) { pior = d; indice = i }
    }

    if (pior <= tolerancia) return [lista[0], lista[lista.length - 1]]

    return [
      ...rdp(lista.slice(0, indice + 1)).slice(0, -1),
      ...rdp(lista.slice(indice)),
    ]
  }

  // Fecha o anel para simplificar, e reabre no fim.
  const fechado = [...pontos, pontos[0]]
  const simples = rdp(fechado)

  return simples.slice(0, -1)
}

/**
 * Área do polígono em km² (fórmula do produto vetorial esférico).
 *
 * Fórmula esférica e não planar: em latitude de −25° o erro de tratar graus
 * como plano passa de 10%, e a área é usada para dimensionar setor.
 */
export function areaKm2(pontos: Ponto[]): number {
  if (pontos.length < 3) return 0

  const rad = Math.PI / 180
  let total = 0

  for (let i = 0; i < pontos.length; i++) {
    const a = pontos[i]
    const b = pontos[(i + 1) % pontos.length]
    total += (b.lng - a.lng) * rad * (2 + Math.sin(a.lat * rad) + Math.sin(b.lat * rad))
  }

  return Math.abs((total * RAIO_TERRA * RAIO_TERRA) / 2) / 1_000_000
}

/** Perímetro em km. */
export function perimetroKm(pontos: Ponto[]): number {
  if (pontos.length < 2) return 0

  let total = 0
  for (let i = 0; i < pontos.length; i++) {
    total += metrosEntre(pontos[i], pontos[(i + 1) % pontos.length])
  }

  return total / 1000
}

/** Ponto médio entre dois — onde nasce o marcador de inserção. */
export function meio(a: Ponto, b: Ponto): Ponto {
  return { lat: (a.lat + b.lat) / 2, lng: (a.lng + b.lng) / 2 }
}
