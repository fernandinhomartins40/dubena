import { Spline, Trash2, Minus, X } from 'lucide-react'
import { Button } from '@/components/ui'
import type { PosicaoTela } from './useEditorCerca'

/**
 * Card de ferramentas do vértice selecionado.
 *
 * Aparece colado no ponto clicado, como o painel de ponto do Illustrator: quem
 * seleciona uma esquina quer agir sobre AQUELA esquina, e ter que ir até uma
 * barra no canto da tela quebra o gesto.
 *
 * O card é HTML sobreposto ao mapa, posicionado em pixels — o hook faz a
 * projeção de latitude/longitude para coordenada de tela e reposiciona a cada
 * arrasto ou zoom.
 */

interface Props {
  /** Número do vértice, como aparece no pino (1-based). */
  numero: number
  posicao: PosicaoTela
  /** Largura e altura do container do mapa, para não deixar o card sair dele. */
  limites: { largura: number; altura: number }
  /** Remover fica indisponível com 3 vértices: menos que isso não é polígono. */
  podeRemover: boolean
  onCurvar: (intensidade: number) => void
  onAlinhar: () => void
  onRemover: () => void
  onFechar: () => void
}

/** Medidas do card, usadas para mantê-lo dentro do mapa. */
const LARGURA = 208
const ALTURA = 132
/** Folga entre o pino e o card, para o card não cobrir o ponto selecionado. */
const FOLGA = 18

export function CardVertice({
  numero, posicao, limites, podeRemover, onCurvar, onAlinhar, onRemover, onFechar,
}: Props) {
  // Perto da borda direita ou de baixo o card vira para o outro lado do pino,
  // senão ficaria cortado pelo container do mapa.
  const x = posicao.x + FOLGA + LARGURA > limites.largura
    ? Math.max(4, posicao.x - FOLGA - LARGURA)
    : posicao.x + FOLGA
  const y = posicao.y + ALTURA > limites.altura
    ? Math.max(4, limites.altura - ALTURA - 4)
    : posicao.y

  return (
    <div
      className="absolute z-20 w-52 rounded-lg border bg-card p-2 shadow-lg"
      style={{ left: x, top: y }}
    >
      <div className="mb-2 flex items-center justify-between px-1">
        <span className="text-xs font-medium">Ponto {numero}</span>
        <button
          type="button" onClick={onFechar}
          className="text-muted-foreground hover:text-foreground"
          title="Fechar"
        >
          <X size={13} />
        </button>
      </div>

      <div className="space-y-1.5">
        <div className="flex items-center gap-1 px-1">
          <Spline size={13} className="shrink-0 text-muted-foreground" />
          <span className="text-[11px] text-muted-foreground">Curvatura</span>
        </div>
        {/* Três intensidades em vez de um controle contínuo: no mapa a diferença
            entre 0,45 e 0,55 é invisível, e botão discreto é mais rápido de
            acertar com o mouse do que arrastar um slider. */}
        <div className="grid grid-cols-3 gap-1">
          {([['Leve', 0.3], ['Média', 0.6], ['Forte', 0.9]] as const).map(([rotulo, forca]) => (
            <Button
              key={rotulo} variant="secondary" size="sm" className="h-7 text-[11px]"
              onClick={() => onCurvar(forca)}
              title={`Arredondar este canto (${rotulo.toLowerCase()})`}
            >
              {rotulo}
            </Button>
          ))}
        </div>

        <div className="flex gap-1 pt-0.5">
          <Button
            variant="ghost" size="sm" className="h-7 flex-1 justify-start text-[11px]"
            onClick={onAlinhar} title="Alinhar com os vizinhos (endireita o trecho)"
          >
            <Minus size={13} /> Alinhar
          </Button>
          <Button
            variant="ghost" size="sm"
            className="h-7 flex-1 justify-start text-[11px] text-destructive"
            disabled={!podeRemover} onClick={onRemover}
            title={podeRemover ? 'Remover este ponto' : 'Um polígono precisa de ao menos 3 pontos'}
          >
            <Trash2 size={13} /> Remover
          </Button>
        </div>
      </div>
    </div>
  )
}
