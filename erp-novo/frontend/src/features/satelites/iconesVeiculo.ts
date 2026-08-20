/**
 * Ícones da frota no mapa ao vivo.
 *
 * O mapa desenhava todo veículo como um círculo cinza: no meio de uma frota de
 * 23 aparelhos não dava para distinguir o caminhão de entrega da moto, nem saber
 * para onde cada um seguia.
 *
 * São paths SVG e não imagens PNG porque o Google Maps sabe **girar** um símbolo
 * pelo `rotation` — é assim que o ícone aponta para a direção da viagem. Com
 * imagem seria preciso um arquivo por ângulo.
 *
 * Os paths são desenhados apontando para CIMA (norte, 0°), centrados na origem,
 * porque o `rotation` do Google gira em torno da âncora e mede o ângulo a partir
 * do norte, no sentido horário — o mesmo referencial do azimute do GPS.
 */

/** Silhuetas vistas de cima, apontando para o norte. */
const CAMINHAO =
  'M -7,-14 L 7,-14 L 8,-8 L 8,13 L -8,13 L -8,-8 Z '
  + 'M -6,-11 L 6,-11 L 6,-7 L -6,-7 Z'

const CAMINHONETE =
  'M -6,-13 L 6,-13 L 7,-6 L 7,12 L -7,12 L -7,-6 Z '
  + 'M -5,-10 L 5,-10 L 5,-6 L -5,-6 Z'

const CARRO =
  'M -5,-12 C -2,-14 2,-14 5,-12 L 6,-4 L 6,10 C 3,12 -3,12 -6,10 L -6,-4 Z '
  + 'M -4,-9 L 4,-9 L 4,-5 L -4,-5 Z'

const MOTO =
  'M 0,-13 L 3,-7 L 3,4 L 6,10 L -6,10 L -3,4 L -3,-7 Z'

/** Fallback: seta simples, ainda mostra a direção. */
const GENERICO = 'M 0,-12 L 8,10 L 0,5 L -8,10 Z'

const POR_ICONE: Record<string, string> = {
  caminhao: CAMINHAO,
  caminhonete: CAMINHONETE,
  carro: CARRO,
  moto: MOTO,
  outro: GENERICO,
}

/**
 * Escolhe a silhueta a partir do tipo do veículo.
 *
 * Aceita tanto o campo `icone` (rótulo curto que o ETL gravou) quanto a
 * descrição livre do tipo: cadastro feito à mão na tela não passa pelo ETL e
 * pode ter qualquer texto.
 */
export function pathDoVeiculo(icone?: string | null, tipo?: string | null): string {
  const chave = (icone ?? '').trim().toLowerCase()
  if (chave && POR_ICONE[chave]) return POR_ICONE[chave]

  const texto = (tipo ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')

  if (texto.includes('caminhonete') || texto.includes('utilitario')) return CAMINHONETE
  if (texto.includes('caminh')) return CAMINHAO
  if (texto.includes('moto')) return MOTO
  if (texto.includes('carro') || texto.includes('automovel')) return CARRO

  return GENERICO
}

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
