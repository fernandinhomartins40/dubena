/**
 * Ícones da frota no mapa ao vivo.
 *
 * São os desenhos da **lucide-react** — a mesma biblioteca do resto do sistema —
 * e não silhuetas próprias. A primeira tentativa desenhou os veículos à mão em
 * `SymbolPath`, e o resultado parecia uma seta genérica: forma inventada não
 * compete com um ícone desenhado por quem desenha ícones.
 *
 * O Google Maps não aceita componente React como marcador, então o SVG é
 * montado aqui com a geometria exata de cada ícone e entregue como `data:` URI.
 * A geometria foi copiada de `lucide-react/dist/esm/icons/*.js`; se a biblioteca
 * for atualizada e algum ícone mudar de traço, é aqui que se acerta.
 *
 * **Por que ícone fixo e não girado.** Os desenhos da lucide são vistos de lado
 * (o carro aponta para a direita). Girar um carro de perfil para o azimute o
 * deixaria de cabeça para baixo em qualquer rumo oeste. A direção vai numa seta
 * pequena ao lado — o ícone continua legível e a informação não se perde.
 */

/** Traços de cada ícone, no viewBox 24×24 da lucide. */
const DESENHOS: Record<string, string> = {
  caminhao:
    '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/>'
    + '<path d="M15 18H9"/>'
    + '<path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/>'
    + '<circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',

  carro:
    '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>'
    + '<circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/>',

  // Caminhonete usa o desenho de utilitário/caravan: carroceria alta, mais
  // próxima de uma Strada ou Saveiro que o sedã do ícone `car`.
  caminhonete:
    '<path d="M18 19V9a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v8a2 2 0 0 0 2 2h2"/>'
    + '<path d="M2 9h3a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H2"/>'
    + '<path d="M22 17v1a1 1 0 0 1-1 1H10v-9a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v9"/>'
    + '<circle cx="8" cy="19" r="2"/>',

  moto:
    '<circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/>'
    + '<circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/>',
}

/** Sem tipo definido: o carro é o veículo mais comum da frota. */
const PADRAO = DESENHOS.carro

/**
 * Escolhe o desenho a partir do tipo do veículo.
 *
 * Aceita tanto o campo `icone` (rótulo curto que o ETL gravou) quanto a
 * descrição livre do tipo: cadastro feito à mão na tela não passa pelo ETL e
 * pode ter qualquer texto.
 */
function desenhoDe(icone?: string | null, tipo?: string | null): string {
  const chave = (icone ?? '').trim().toLowerCase()
  if (chave && DESENHOS[chave]) return DESENHOS[chave]

  const texto = (tipo ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')

  if (texto.includes('caminhonete') || texto.includes('utilitario')) return DESENHOS.caminhonete
  if (texto.includes('caminh') || texto.includes('carreta')) return DESENHOS.caminhao
  if (texto.includes('moto')) return DESENHOS.moto
  if (texto.includes('carro') || texto.includes('automovel')) return DESENHOS.carro

  return PADRAO
}

/** Lado do quadrado do marcador, em pixels. */
const TAMANHO = 40

/**
 * Marcador completo: disco colorido, ícone do veículo e seta da direção.
 *
 * O disco existe para o ícone ter contraste sobre qualquer mapa — traço fino
 * sobre foto de satélite ou sobre uma cerca colorida some. A cor do disco é o
 * estado operacional, que é a informação que se lê de longe.
 *
 * @param direcao azimute em graus; sem ela a seta não é desenhada
 */
export function svgDoVeiculo(
  icone: string | null | undefined,
  tipo: string | null | undefined,
  cor: string,
  direcao?: number | null,
): string {
  const raio = 13
  const centro = TAMANHO / 2

  // A seta orbita o disco na direção da viagem. `-90` porque o azimute conta do
  // norte no sentido horário e o seno/cosseno contam do leste.
  let seta = ''
  if (direcao !== null && direcao !== undefined) {
    const rad = ((direcao - 90) * Math.PI) / 180
    const dx = centro + Math.cos(rad) * (raio + 4)
    const dy = centro + Math.sin(rad) * (raio + 4)
    seta = `<g transform="translate(${dx.toFixed(1)} ${dy.toFixed(1)}) rotate(${direcao})">`
      + `<path d="M0,-4 L3,3 L0,1.4 L-3,3 Z" fill="${cor}" stroke="#fff" stroke-width="1"/>`
      + '</g>'
  }

  // O ícone da lucide vem em 24×24: escalar para 17 e centralizar deixa o
  // desenho dentro do disco com folga para o traço não encostar na borda.
  const escala = 17 / 24
  const deslocamento = centro - (24 * escala) / 2

  const svg =
    `<svg xmlns="http://www.w3.org/2000/svg" width="${TAMANHO}" height="${TAMANHO}" viewBox="0 0 ${TAMANHO} ${TAMANHO}">`
    + `<circle cx="${centro}" cy="${centro}" r="${raio}" fill="${cor}" stroke="#fff" stroke-width="2.5"/>`
    + seta
    + `<g transform="translate(${deslocamento.toFixed(2)} ${deslocamento.toFixed(2)}) scale(${escala.toFixed(4)})"`
    + ' fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">'
    + desenhoDe(icone, tipo)
    + '</g></svg>'

  // `encodeURIComponent` e não base64: o SVG continua legível no inspetor, e a
  // string fica menor — são até 25 marcadores redesenhados a cada 30 s.
  return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`
}

/** Tamanho do marcador, para o mapa ancorar o ícone pelo centro. */
export const TAMANHO_MARCADOR = TAMANHO

/** Estado operacional do veículo, que decide a cor no mapa. */
export type EstadoVeiculo = 'excesso' | 'movimento' | 'ligado' | 'parado' | 'sem_sinal'

/**
 * Minutos sem reportar a partir dos quais o veículo é dado como sem sinal.
 *
 * O polling roda a cada 30 s; 15 minutos é folgado o bastante para não acusar
 * falsamente um veículo em área de sombra, e curto o bastante para o operador
 * perceber um rastreador que caiu.
 */
const MINUTOS_SEM_SINAL = 15

export function estadoDoVeiculo(p: {
  velocidade: number
  ignicao: boolean
  excesso?: boolean
  registrado_em?: string | null
}): EstadoVeiculo {
  if (p.registrado_em) {
    const minutos = (Date.now() - new Date(p.registrado_em).getTime()) / 60000
    if (minutos > MINUTOS_SEM_SINAL) return 'sem_sinal'
  }
  if (p.excesso) return 'excesso'
  if (p.velocidade > 1) return 'movimento'
  if (p.ignicao) return 'ligado'

  return 'parado'
}

export const CORES_ESTADO: Record<EstadoVeiculo, string> = {
  // Vermelho só para excesso de velocidade: é a única condição que pede ação
  // imediata de quem está olhando o mapa.
  excesso: '#EF4444',
  movimento: '#22C55E',
  // Ligado e parado (motor em marcha lenta) merece cor própria: é combustível
  // queimando sem entrega, e o legado não distinguia isso de veículo desligado.
  ligado: '#F59E0B',
  parado: '#6B7280',
  sem_sinal: '#9CA3AF',
}

export const ROTULOS_ESTADO: Record<EstadoVeiculo, string> = {
  excesso: 'Acima da velocidade',
  movimento: 'Em movimento',
  ligado: 'Ligado e parado',
  parado: 'Parado',
  sem_sinal: 'Sem sinal',
}
