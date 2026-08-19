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
 * **Por que não Catmull-Rom.** A primeira versão usava Catmull-Rom, que tem a
 * propriedade elegante de passar pelos vértices marcados. Só que ela faz
 * *overshoot*: a curva estufa para FORA do contorno. No quadrado de teste
 * inflou a área em 35% — cerca de 150 m de invasão em cada lado, uma quadra
 * inteira entrando no setor sem ninguém ter desenhado isso. Reduzir a tensão
 * amenizava (15%) mas não resolvia; zerar tornava a curva invisível.
 *
 * A Bézier quadrática de canto não tem esse problema por construção: ela vive
 * dentro do triângulo dos seus pontos de controle, então a curva nunca sai do
 * polígono original. O preço é que o vértice marcado deixa de existir — vira
 * arco entre os vizinhos — que é exatamente o que se espera de "arredondar
 * canto", e é a mesma operação de `arredondarCanto`, aplicada a todos.
 *
 * @param intensidade 0..1, quanto do segmento vizinho a curva ocupa
 * @param passos segmentos gerados em cada arco
 */
export function suavizar(pontos: Ponto[], intensidade = 0.6, passos = 6): Ponto[] {
  if (pontos.length < 3) return pontos

  const forca = Math.max(0, Math.min(1, intensidade))
  if (forca === 0) return pontos

  const n = pontos.length
  const saida: Ponto[] = []
  const t = forca * 0.5

  for (let i = 0; i < n; i++) {
    // O polígono é fechado: os vizinhos dão a volta no fim da lista.
    const anterior = pontos[(i - 1 + n) % n]
    const canto = pontos[i]
    const proximo = pontos[(i + 1) % n]

    const entrada = {
      lat: canto.lat + (anterior.lat - canto.lat) * t,
      lng: canto.lng + (anterior.lng - canto.lng) * t,
    }
    const saidaCanto = {
      lat: canto.lat + (proximo.lat - canto.lat) * t,
      lng: canto.lng + (proximo.lng - canto.lng) * t,
    }

    for (let k = 0; k <= passos; k++) {
      const s = k / passos
      const u = 1 - s
      saida.push({
        lat: u * u * entrada.lat + 2 * u * s * canto.lat + s * s * saidaCanto.lat,
        lng: u * u * entrada.lng + 2 * u * s * canto.lng + s * s * saidaCanto.lng,
      })
    }
  }

  return saida
}

/**
 * Arredonda UM canto do contorno, sem tocar no resto.
 *
 * O botão global de suavizar aplicava curva em todos os vértices de uma vez —
 * quem queria arredondar uma esquina acabava com o quarteirão inteiro redondo.
 * Aqui o vértice `i` vira um arco entre os seus dois vizinhos, como a ferramenta
 * de canto do Illustrator: o ponto original desaparece e no lugar dele entra a
 * curva.
 *
 * `intensidade` (0..1) é quanto do segmento vizinho a curva ocupa. Em 0 o canto
 * volta a ser reto; em 1 o arco encosta nos vizinhos e o canto some por inteiro.
 * A curva é DENSIFICADA em vértices pelo mesmo motivo de `suavizar`: o polígono
 * continua sendo uma lista de pontos, e nada no geofencing precisa mudar.
 *
 * @param passos segmentos gerados no arco
 */
export function arredondarCanto(
  pontos: Ponto[],
  i: number,
  intensidade = 0.5,
  passos = 8,
): Ponto[] {
  const n = pontos.length
  if (n < 3 || i < 0 || i >= n) return pontos

  const forca = Math.max(0, Math.min(1, intensidade))
  if (forca === 0) return pontos

  const anterior = pontos[(i - 1 + n) % n]
  const canto = pontos[i]
  const proximo = pontos[(i + 1) % n]

  // Metade é o limite geométrico: passar disso invadiria o canto vizinho e as
  // duas curvas se cruzariam.
  const t = (forca * 0.5)
  const entrada = {
    lat: canto.lat + (anterior.lat - canto.lat) * t,
    lng: canto.lng + (anterior.lng - canto.lng) * t,
  }
  const saida = {
    lat: canto.lat + (proximo.lat - canto.lat) * t,
    lng: canto.lng + (proximo.lng - canto.lng) * t,
  }

  // Bézier quadrática com o canto original como ponto de controle: o arco sai
  // tangente às duas retas, que é o que dá a aparência de canto arredondado.
  const arco: Ponto[] = []
  for (let k = 0; k <= passos; k++) {
    const s = k / passos
    const u = 1 - s
    arco.push({
      lat: u * u * entrada.lat + 2 * u * s * canto.lat + s * s * saida.lat,
      lng: u * u * entrada.lng + 2 * u * s * canto.lng + s * s * saida.lng,
    })
  }

  return [...pontos.slice(0, i), ...arco, ...pontos.slice(i + 1)]
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
