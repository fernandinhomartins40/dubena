import { describe, expect, it } from 'vitest'
import {
  areaKm2, arredondarCanto, meio, metrosEntre, perimetroKm, simplificar, suavizar, type Ponto,
} from './editorPoligono'

/**
 * Geometria do editor de cercas.
 *
 * A interação com o Google Maps não é testável aqui, mas o cálculo é — e é onde
 * um erro passa despercebido: uma área errada não parece errada na tela, só
 * dimensiona o setor errado.
 */

/** Quadrado em Guarapuava, ~1,1 km de lado. */
const QUADRADO: Ponto[] = [
  { lat: -25.390, lng: -51.460 },
  { lat: -25.390, lng: -51.449 },
  { lat: -25.400, lng: -51.449 },
  { lat: -25.400, lng: -51.460 },
]

describe('metrosEntre', () => {
  it('mede um grau de latitude em ~111 km', () => {
    const d = metrosEntre({ lat: 0, lng: 0 }, { lat: 1, lng: 0 })
    expect(d).toBeGreaterThan(110_000)
    expect(d).toBeLessThan(112_000)
  })

  it('é zero entre o mesmo ponto', () => {
    expect(metrosEntre(QUADRADO[0], QUADRADO[0])).toBe(0)
  })
})

describe('areaKm2', () => {
  /**
   * O motivo de a fórmula ser esférica e não planar: em latitude −25° tratar
   * grau como plano erra mais de 10%, e a área é o que dimensiona o setor.
   */
  it('mede o quadrado de Guarapuava em ~1,2 km²', () => {
    const area = areaKm2(QUADRADO)
    expect(area).toBeGreaterThan(1.0)
    expect(area).toBeLessThan(1.5)
  })

  it('não depende do sentido do desenho (horário ou anti-horário)', () => {
    expect(areaKm2([...QUADRADO].reverse())).toBeCloseTo(areaKm2(QUADRADO), 6)
  })

  it('é zero com menos de três pontos', () => {
    expect(areaKm2(QUADRADO.slice(0, 2))).toBe(0)
  })
})

describe('perimetroKm', () => {
  it('fecha o anel — o último ponto liga no primeiro', () => {
    // Quatro lados de ~1,1 km cada.
    const p = perimetroKm(QUADRADO)
    expect(p).toBeGreaterThan(4.0)
    expect(p).toBeLessThan(4.6)
  })
})

describe('suavizar', () => {
  it('densifica em vértices, mantendo o polígono uma lista de pontos', () => {
    const curvo = suavizar(QUADRADO, 0.6, 6)
    // Um arco de 7 pontos (passos + 1) por canto.
    expect(curvo).toHaveLength(QUADRADO.length * 7)
    // O geofencing atual só entende lista de pontos: se virasse outra estrutura,
    // o rastreador pararia de reconhecer a cerca.
    curvo.forEach((p) => {
      expect(typeof p.lat).toBe('number')
      expect(typeof p.lng).toBe('number')
    })
  })

  /**
   * Arredondar canto SEMPRE encolhe: o bico é cortado. O que não pode é a área
   * crescer — cerca que estufa passa a cobrir rua do setor vizinho. Encolher um
   * pouco é o lado seguro do erro.
   */
  it('encolhe a área um pouco e nunca a aumenta', () => {
    const antes = areaKm2(QUADRADO)
    const depois = areaKm2(suavizar(QUADRADO))

    expect(depois).toBeLessThan(antes)
    expect(depois).toBeGreaterThan(antes * 0.9)
  })

  /**
   * Este é o teste que pegou um defeito real: a Catmull-Rom clássica estufava a
   * curva 150 m para fora do contorno, inflando a área em 35%. Numa cerca de
   * bairro é uma quadra inteira entrando no setor sem ninguém ter desenhado.
   */
  it('não estoura para fora do contorno original', () => {
    const curvo = suavizar(QUADRADO)
    const minLat = Math.min(...QUADRADO.map((p) => p.lat))
    const maxLat = Math.max(...QUADRADO.map((p) => p.lat))
    const minLng = Math.min(...QUADRADO.map((p) => p.lng))
    const maxLng = Math.max(...QUADRADO.map((p) => p.lng))

    // Tolerância de 1e-4 grau ≈ 11 m: curvatura visível, sem invadir quadra.
    curvo.forEach((p) => {
      expect(p.lat).toBeGreaterThanOrEqual(minLat - 1e-4)
      expect(p.lat).toBeLessThanOrEqual(maxLat + 1e-4)
      expect(p.lng).toBeGreaterThanOrEqual(minLng - 1e-4)
      expect(p.lng).toBeLessThanOrEqual(maxLng + 1e-4)
    })
  })

  it('deixa passar contorno com menos de três pontos', () => {
    const dois = QUADRADO.slice(0, 2)
    expect(suavizar(dois)).toBe(dois)
  })
})

describe('arredondarCanto', () => {
  /**
   * O defeito relatado: o botão de curva arredondava tudo. Aqui o contrato é
   * que só o canto pedido muda.
   */
  it('mexe só no canto indicado — os outros vértices ficam idênticos', () => {
    const saida = arredondarCanto(QUADRADO, 1, 0.5, 8)

    // Os vértices 0, 2 e 3 sobrevivem intactos em algum lugar da lista.
    for (const indice of [0, 2, 3]) {
      const original = QUADRADO[indice]
      const achou = saida.some((p) => p.lat === original.lat && p.lng === original.lng)
      expect(achou, `o vértice ${indice} foi alterado`).toBe(true)
    }
  })

  it('substitui o vértice do canto por um arco', () => {
    const saida = arredondarCanto(QUADRADO, 1, 0.5, 8)
    const canto = QUADRADO[1]

    expect(saida.some((p) => p.lat === canto.lat && p.lng === canto.lng)).toBe(false)
    expect(saida.length).toBeGreaterThan(QUADRADO.length)
  })

  it('com intensidade 0 devolve o contorno inalterado', () => {
    expect(arredondarCanto(QUADRADO, 1, 0)).toEqual(QUADRADO)
  })

  /**
   * A intensidade é limitada a metade do segmento: passar disso faria a curva
   * invadir o canto vizinho e as duas se cruzariam.
   */
  it('mesmo na intensidade máxima o arco não passa do meio dos segmentos', () => {
    const saida = arredondarCanto(QUADRADO, 1, 1, 8)
    const meioAnterior = meio(QUADRADO[0], QUADRADO[1])
    const meioProximo = meio(QUADRADO[1], QUADRADO[2])

    // O arco entra no lugar do vértice 1: são os pontos entre o vértice 0 e o 2
    // na lista de saída. Os demais vértices do quadrado não são arco.
    const inicio = saida.findIndex((p) => p.lat === QUADRADO[0].lat && p.lng === QUADRADO[0].lng)
    const fim = saida.findIndex((p) => p.lat === QUADRADO[2].lat && p.lng === QUADRADO[2].lng)
    const arco = saida.slice(inicio + 1, fim)

    expect(arco.length).toBeGreaterThan(0)
    arco.forEach((p) => {
      // O arco vive dentro do retângulo formado pelos dois pontos médios e o
      // canto original — passar disso invadiria a curva do canto vizinho.
      expect(p.lng).toBeGreaterThanOrEqual(Math.min(meioAnterior.lng, meioProximo.lng) - 1e-9)
      expect(p.lng).toBeLessThanOrEqual(Math.max(meioAnterior.lng, meioProximo.lng) + 1e-9)
      expect(p.lat).toBeGreaterThanOrEqual(Math.min(meioAnterior.lat, meioProximo.lat) - 1e-9)
      expect(p.lat).toBeLessThanOrEqual(Math.max(meioAnterior.lat, meioProximo.lat) + 1e-9)
    })
  })

  it('ignora índice fora da lista', () => {
    expect(arredondarCanto(QUADRADO, 9)).toEqual(QUADRADO)
    expect(arredondarCanto(QUADRADO, -1)).toEqual(QUADRADO)
  })

  it('a área muda pouco — arredondar canto não redesenha o setor', () => {
    const saida = arredondarCanto(QUADRADO, 1, 0.6)
    expect(areaKm2(saida)).toBeCloseTo(areaKm2(QUADRADO), 1)
  })
})

describe('simplificar', () => {
  it('desfaz a densificação da curva, voltando perto do original', () => {
    const curvo = suavizar(QUADRADO, 6)
    const simples = simplificar(curvo, 30)

    expect(simples.length).toBeLessThan(curvo.length)
    expect(areaKm2(simples)).toBeCloseTo(areaKm2(QUADRADO), 0)
  })

  it('não mexe em contorno que já é mínimo', () => {
    const triangulo = QUADRADO.slice(0, 3)
    expect(simplificar(triangulo)).toEqual(triangulo)
  })
})
