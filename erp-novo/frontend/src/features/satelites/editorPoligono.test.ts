import { describe, expect, it } from 'vitest'
import {
  areaKm2, cascoConvexo, meio, metrosEntre, perimetroKm, simplificar, tracado,
  type No, type Ponto,
} from './editorPoligono'

/**
 * Geometria do editor de cercas.
 *
 * A interação com o Google Maps não é testável aqui, mas o cálculo é — e é onde
 * um erro passa despercebido: uma área errada não parece errada na tela, só
 * dimensiona o setor errado.
 */

/** Quadrado em Guarapuava, ~1,1 km de lado, todo em canto. */
const QUADRADO: No[] = [
  { lat: -25.390, lng: -51.460, liso: false },
  { lat: -25.390, lng: -51.449, liso: false },
  { lat: -25.400, lng: -51.449, liso: false },
  { lat: -25.400, lng: -51.460, liso: false },
]

/** O mesmo contorno com o nó 1 curvo — o caso de uso real. */
const COM_CURVA: No[] = QUADRADO.map((p, i) => ({ ...p, liso: i === 1 }))

const so = (nos: No[]): Ponto[] => nos.map(({ lat, lng }) => ({ lat, lng }))

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
    const area = areaKm2(so(QUADRADO))
    expect(area).toBeGreaterThan(1.0)
    expect(area).toBeLessThan(1.5)
  })

  it('não depende do sentido do desenho (horário ou anti-horário)', () => {
    expect(areaKm2(so([...QUADRADO].reverse()))).toBeCloseTo(areaKm2(so(QUADRADO)), 6)
  })

  it('é zero com menos de três pontos', () => {
    expect(areaKm2(so(QUADRADO).slice(0, 2))).toBe(0)
  })
})

describe('perimetroKm', () => {
  it('fecha o anel — o último ponto liga no primeiro', () => {
    // Quatro lados de ~1,1 km cada.
    const p = perimetroKm(so(QUADRADO))
    expect(p).toBeGreaterThan(4.0)
    expect(p).toBeLessThan(4.6)
  })
})

describe('tracado', () => {
  /**
   * Contorno só de cantos tem de sair idêntico. Se a curva vazasse para nós de
   * canto, toda cerca migrada do legado mudaria de forma ao ser aberta — e
   * ninguém pediu isso.
   */
  it('sem nó liso, devolve exatamente os pontos originais', () => {
    expect(tracado(QUADRADO)).toEqual(so(QUADRADO))
  })

  it('com nó liso, densifica só os trechos vizinhos a ele', () => {
    const linha = tracado(COM_CURVA)

    expect(linha.length).toBeGreaterThan(QUADRADO.length)
    // Os dois lados que não tocam o nó 1 continuam sendo uma reta de um ponto.
    expect(linha.length).toBeLessThan(QUADRADO.length * 12)
  })

  /**
   * A propriedade central da ferramenta: o nó continua existindo depois de
   * virar curva. Era isso que faltava na versão que densificava o contorno — o
   * ponto sumia e não havia mais o que arrastar.
   */
  it('a curva passa pelo nó liso, que não desaparece', () => {
    const linha = tracado(COM_CURVA)
    const no = COM_CURVA[1]

    expect(linha.some((p) => p.lat === no.lat && p.lng === no.lng)).toBe(true)
  })

  it('devolve os nós crus quando há menos de três', () => {
    const dois = QUADRADO.slice(0, 2)
    expect(tracado(dois)).toEqual(so(dois))
  })

  /**
   * Este é o teste que pegou um defeito real: sem limitar a tangente, a curva
   * estufava ~150 m para FORA do contorno e inflava a área em 36%. Numa cerca
   * de bairro é uma quadra inteira entrando no setor sem ninguém ter desenhado.
   */
  it('a curva não estoura muito além do contorno dos nós', () => {
    const linha = tracado(COM_CURVA)
    const maxLng = Math.max(...QUADRADO.map((p) => p.lng))

    // 5e-4 grau ≈ 50 m. Medido: um nó liso num canto de 90° estoura ~34 m, que
    // é a curvatura pedida, não invasão. Sem a trava da tangente eram 150 m.
    linha.forEach((p) => {
      expect(p.lng).toBeLessThanOrEqual(maxLng + 5e-4)
    })
  })

  /**
   * Contorno realista — poucos nós lisos entre cantos, que é como se desenha um
   * bairro. Aqui o desvio de área tem de ser desprezível.
   */
  it('em contorno realista a área quase não muda ao curvar', () => {
    const bairro: No[] = [
      { lat: -25.3900, lng: -51.4600, liso: false },
      { lat: -25.3885, lng: -51.4550, liso: true },
      { lat: -25.3900, lng: -51.4490, liso: false },
      { lat: -25.3955, lng: -51.4475, liso: true },
      { lat: -25.4000, lng: -51.4490, liso: false },
      { lat: -25.4000, lng: -51.4600, liso: false },
    ]

    const antes = areaKm2(so(bairro))
    const depois = areaKm2(tracado(bairro))

    expect(Math.abs(depois / antes - 1)).toBeLessThan(0.05)
  })

  it('todos os nós lisos produz um contorno fechado e válido', () => {
    const redondo = QUADRADO.map((p) => ({ ...p, liso: true }))
    const linha = tracado(redondo)

    expect(linha.length).toBe(QUADRADO.length * 12)
    expect(areaKm2(linha)).toBeGreaterThan(0)
  })
})

describe('simplificar', () => {
  it('reduz um traçado densificado sem descaracterizar a área', () => {
    const linha = tracado(QUADRADO.map((p) => ({ ...p, liso: true })))
    const simples = simplificar(linha, 30)

    expect(simples.length).toBeLessThan(linha.length)
    expect(areaKm2(simples)).toBeCloseTo(areaKm2(linha), 0)
  })

  it('não mexe em contorno que já é mínimo', () => {
    const triangulo = so(QUADRADO).slice(0, 3)
    expect(simplificar(triangulo)).toEqual(triangulo)
  })
})

describe('meio', () => {
  it('fica entre os dois pontos', () => {
    const m = meio(QUADRADO[0], QUADRADO[1])
    expect(m.lng).toBeCloseTo((QUADRADO[0].lng + QUADRADO[1].lng) / 2, 9)
    expect(m.lat).toBeCloseTo(QUADRADO[0].lat, 9)
  })
})

describe('cascoConvexo', () => {
  it('descarta o ponto interno e mantem os quatro cantos', () => {
    const casco = cascoConvexo([
      { lat: 0, lng: 0 },
      { lat: 0, lng: 2 },
      { lat: 2, lng: 2 },
      { lat: 2, lng: 0 },
      // No meio: nao pode sobreviver, senao a uniao de quadras acumularia
      // pontos internos inuteis a cada clique.
      { lat: 1, lng: 1 },
    ])

    expect(casco).toHaveLength(4)
    expect(casco.some((p) => p.lat === 1 && p.lng === 1)).toBe(false)
  })

  it('une duas quadras vizinhas cobrindo as duas', () => {
    const a = [
      { lat: 0, lng: 0 }, { lat: 0, lng: 1 }, { lat: 1, lng: 1 }, { lat: 1, lng: 0 },
    ]
    const b = [
      { lat: 0, lng: 1 }, { lat: 0, lng: 2 }, { lat: 1, lng: 2 }, { lat: 1, lng: 1 },
    ]

    const uniao = cascoConvexo([...a, ...b])

    // A area precisa ser a soma das duas: uniao que perde metade do territorio
    // seria pior que nao unir.
    const lats = uniao.map((p) => p.lat)
    const lngs = uniao.map((p) => p.lng)
    expect(Math.min(...lngs)).toBe(0)
    expect(Math.max(...lngs)).toBe(2)
    expect(Math.min(...lats)).toBe(0)
    expect(Math.max(...lats)).toBe(1)
  })

  it('devolve o proprio conjunto quando ha tres pontos ou menos', () => {
    const tri = [{ lat: 0, lng: 0 }, { lat: 0, lng: 1 }, { lat: 1, lng: 0 }]
    expect(cascoConvexo(tri)).toEqual(tri)
  })
})
