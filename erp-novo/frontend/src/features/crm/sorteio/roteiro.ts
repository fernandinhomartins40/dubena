/**
 * Roteiro da roleta: a sequência de números que passa na tela até parar no
 * ganhador, com o intervalo de cada troca.
 *
 * É função pura de propósito. O ganhador vem do servidor ANTES da animação; o
 * roteiro só precisa garantir duas coisas que dá para testar sem navegador:
 * termina exatamente nele, e desacelera — a parada lenta é o que cria o
 * suspense, e uma roleta que para de repente parece truque.
 */
export interface Quadro {
  numero: string
  nome: string | null
  /** Quanto tempo este quadro fica na tela antes do próximo (ms). */
  duracao: number
}

export interface Participante {
  numero: string
  nome: string | null
}

/** Curva de desaceleração: rápido no início, arrastando no fim. */
const facilitar = (t: number) => t * t * t

export function montarRoteiro(
  participantes: Participante[],
  ganhador: Participante,
  opcoes: { duracaoTotal?: number; intervaloInicial?: number; intervaloFinal?: number; sortear?: () => number } = {},
): Quadro[] {
  const { duracaoTotal = 7000, intervaloInicial = 45, intervaloFinal = 650, sortear = Math.random } = opcoes
  const pool = participantes.length > 0 ? participantes : [ganhador]

  // Quantos quadros cabem na duração com essa curva: integra até passar.
  const intervalos: number[] = []
  let acumulado = 0
  let i = 0
  while (acumulado < duracaoTotal && i < 2000) {
    // Estimativa de progresso pelo tempo acumulado — a curva acompanha o
    // relógio, não a contagem de quadros.
    const t = Math.min(1, acumulado / duracaoTotal)
    const d = intervaloInicial + (intervaloFinal - intervaloInicial) * facilitar(t)
    intervalos.push(Math.round(d))
    acumulado += d
    i++
  }

  const quadros: Quadro[] = []
  let anterior: string | null = null
  for (let k = 0; k < intervalos.length - 1; k++) {
    let p = pool[Math.floor(sortear() * pool.length)]
    // Com mais de um participante, não repetir o mesmo número em sequência:
    // parece que a roleta travou.
    if (pool.length > 1) {
      for (let tentativa = 0; p.numero === anterior && tentativa < 5; tentativa++) {
        p = pool[Math.floor(sortear() * pool.length)]
      }
    }
    quadros.push({ numero: p.numero, nome: p.nome, duracao: intervalos[k] })
    anterior = p.numero
  }

  // O último quadro é SEMPRE o ganhador, com a pausa mais longa.
  quadros.push({ numero: ganhador.numero, nome: ganhador.nome, duracao: intervalos[intervalos.length - 1] ?? intervaloFinal })

  return quadros
}
