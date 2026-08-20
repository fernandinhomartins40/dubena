/**
 * Geometria do editor de cercas.
 *
 * Isolado do componente de tela porque é cálculo puro — derivar o traçado dos
 * nós, simplificar, medir área e perímetro. Misturar isso com JSX deixava
 * `CercasTab` ilegível e impossível de ajustar sem quebrar o resto. Como é
 * função pura, é também a única parte do editor que dá para testar: a interação
 * com o Google Maps não roda fora do navegador.
 */

export interface Ponto { lat: number; lng: number }

/**
 * Um vértice do contorno, com o estado que a ferramenta Curvatura mantém.
 *
 * `liso` é o que diferencia esta ferramenta de um "arredondar canto": o ponto
 * NÃO desaparece ao virar curva. Ele continua lá, arrastável, e a curva é
 * recalculada a partir dele — como no Illustrator, onde duplo clique alterna
 * entre passar suave pelo ponto e fazer bico nele.
 */
export interface No extends Ponto { liso: boolean }

/** Quantos segmentos de reta aproximam cada trecho curvo ao desenhar. */
const PASSOS_TRACADO = 12

/**
 * Teto da tangente, como fração do menor segmento vizinho.
 *
 * Sem esta trava a curva estufa para fora do contorno — foi o que inflou uma
 * cerca de teste em 36% e a fez cobrir uma quadra que ninguém desenhou. Com
 * 0,33 a curvatura continua evidente e o arco fica contido.
 *
 * Num contorno de bairro real (poucos nós lisos entre cantos) o desvio de área
 * fica abaixo de 1%. Os desvios grandes só surgem quando TODOS os nós são
 * lisos, e aí o usuário está literalmente pedindo um círculo.
 */
const TETO_TANGENTE = 0.33

/**
 * Converte os nós no traçado que aparece no mapa.
 *
 * Trecho entre dois nós de canto é reta; havendo nó liso na ponta, o trecho
 * vira curva. A tangente em cada nó liso é dada pelos seus vizinhos, o que faz
 * a curva atravessar o nó suavemente em vez de dobrar ali.
 *
 * **A curvatura é derivada, não gravada.** O banco guarda só os nós; o traçado
 * é recalculado a cada desenho. É isso que permite arrastar um ponto e ver a
 * curva acompanhar ao vivo — se a curva fosse densificada em vértices, arrastar
 * um deles deformaria o arco em vez de refazê-lo.
 */
export function tracado(nos: No[], passos = PASSOS_TRACADO): Ponto[] {
  const n = nos.length
  if (n < 3) return nos.map(({ lat, lng }) => ({ lat, lng }))

  const saida: Ponto[] = []

  // Tangente de um nó liso: metade do vetor entre os vizinhos, limitada ao
  // menor segmento adjacente. Em nó de canto a tangente é zero — é o que trava
  // a curva e produz o bico.
  const tangente = (i: number): Ponto => {
    if (!nos[i].liso) return { lat: 0, lng: 0 }

    const a = nos[(i - 1 + n) % n]
    const p = nos[i]
    const b = nos[(i + 1) % n]
    const m = { lat: (b.lat - a.lat) / 2, lng: (b.lng - a.lng) / 2 }

    // Distância em graus basta aqui: a comparação é entre segmentos do mesmo
    // contorno, na mesma latitude, e o resultado só escala um vetor.
    const grausAte = (x: Ponto, y: Ponto) => Math.hypot(x.lat - y.lat, x.lng - y.lng)
    const teto = TETO_TANGENTE * Math.min(grausAte(p, a), grausAte(p, b))
    const tamanho = Math.hypot(m.lat, m.lng)
    if (tamanho > teto && tamanho > 0) {
      return { lat: (m.lat * teto) / tamanho, lng: (m.lng * teto) / tamanho }
    }

    return m
  }

  for (let i = 0; i < n; i++) {
    const p1 = nos[i]
    const p2 = nos[(i + 1) % n]

    // Reta entre dois cantos: não gasta pontos aproximando o que já é reto.
    if (!p1.liso && !p2.liso) {
      saida.push({ lat: p1.lat, lng: p1.lng })
      continue
    }

    const m1 = tangente(i)
    const m2 = tangente((i + 1) % n)

    // Hermite cúbica: passa por p1 e p2 com as tangentes dadas. Onde a tangente
    // é zero (canto) ela degenera em algo que sai reto do ponto, que é
    // exatamente o comportamento desejado.
    for (let k = 0; k < passos; k++) {
      const s = k / passos
      const s2 = s * s
      const s3 = s2 * s
      const h1 = 2 * s3 - 3 * s2 + 1
      const h2 = s3 - 2 * s2 + s
      const h3 = -2 * s3 + 3 * s2
      const h4 = s3 - s2

      saida.push({
        lat: h1 * p1.lat + h2 * m1.lat + h3 * p2.lat + h4 * m2.lat,
        lng: h1 * p1.lng + h2 * m1.lng + h3 * p2.lng + h4 * m2.lng,
      })
    }
  }

  return saida
}

/** Raio médio da Terra, em metros — usado nas medidas. */
const RAIO_TERRA = 6_371_008.8

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

/**
 * Casco convexo de um conjunto de pontos (varredura de Andrew).
 *
 * Usado para UNIR quadras ao montar um setor clicando uma a uma. É
 * aproximação: um setor em L sai com o canto preenchido. Vale mesmo assim
 * porque a alternativa — clipper de polígonos completo — é muito código para
 * rodar no navegador, e o operador tem os pontos na mão para corrigir o canto.
 * União errada de geometria, essa sim, ele não teria como consertar.
 */
export function cascoConvexo(pontos: Ponto[]): Ponto[] {
  if (pontos.length <= 3) return pontos

  // Ordena por longitude e depois latitude; o algoritmo depende disso.
  const p = [...pontos].sort((a, b) => (a.lng - b.lng) || (a.lat - b.lat))

  // Produto vetorial: > 0 significa curva à esquerda (mantém), <= 0 descarta.
  const giro = (o: Ponto, a: Ponto, b: Ponto) =>
    (a.lng - o.lng) * (b.lat - o.lat) - (a.lat - o.lat) * (b.lng - o.lng)

  const metade = (lista: Ponto[]): Ponto[] => {
    const saida: Ponto[] = []
    for (const pt of lista) {
      while (saida.length >= 2 && giro(saida[saida.length - 2], saida[saida.length - 1], pt) <= 0) {
        saida.pop()
      }
      saida.push(pt)
    }
    saida.pop() // o último repete o primeiro da outra metade

    return saida
  }

  return [...metade(p), ...metade([...p].reverse())]
}

/** Ponto médio entre dois — onde nasce o marcador de inserção. */
export function meio(a: Ponto, b: Ponto): Ponto {
  return { lat: (a.lat + b.lat) / 2, lng: (a.lng + b.lng) / 2 }
}
