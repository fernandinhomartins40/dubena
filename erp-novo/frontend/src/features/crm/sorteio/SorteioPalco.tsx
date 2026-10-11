import { useCallback, useEffect, useRef, useState } from 'react'
import { Gift, MapPin, Maximize, Minimize, Phone, Play, RotateCcw, Square, Trophy, Video, Volume2, VolumeX, X } from 'lucide-react'
import { usePalcoSorteio, useSortear, type ResultadoSorteio } from '../api'
import { montarRoteiro, type Quadro } from './roteiro'
import { criarSons, iniciarGravacao, soltarConfete, type Sons } from './efeitos'
import './palco.css'

/**
 * Palco do sorteio: o momento do prêmio como evento.
 *
 * Preparo → contagem regressiva → roleta desacelerando → revelação com
 * confete, nome e local do ganhador. Pensado para telão e para ser gravado: os
 * controles somem durante a contagem e a roleta, e o botão "Gravar" gera um
 * vídeo da aba.
 *
 * O resultado é pedido ao servidor no INSTANTE em que a contagem começa e fica
 * registrado lá; a roleta apenas o encena. "Reapresentar" repete a cena com o
 * mesmo ganhador, sem sortear de novo — é assim que se grava outra vez.
 */
type Fase = 'preparo' | 'contagem' | 'girando' | 'revelado'

const CONTAGEM = 5
const CORES_CONFETE = ['#ff6200', '#dbfb3b', '#ffffff', '#ffb27a', '#f5f5f0']

const dataHora = (iso: string | null) =>
  iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'medium' }) : '—'

export function SorteioPalco({ sorteioId, onFechar }: { sorteioId: number; onFechar: () => void }) {
  const { data: palco, isLoading, error } = usePalcoSorteio(sorteioId)
  const sortear = useSortear()

  const raiz = useRef<HTMLDivElement>(null)
  const canvas = useRef<HTMLCanvasElement>(null)
  const sons = useRef<Sons | null>(null)
  const timers = useRef<number[]>([])
  const pararConfete = useRef<(() => void) | null>(null)
  const pararGravacao = useRef<(() => void) | null>(null)

  const [fase, setFase] = useState<Fase>('preparo')
  const [contagem, setContagem] = useState(CONTAGEM)
  const [quadro, setQuadro] = useState<Quadro | null>(null)
  const [indice, setIndice] = useState(0)
  const [ultimo, setUltimo] = useState(-1)
  const [resultado, setResultado] = useState<ResultadoSorteio | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [comSom, setComSom] = useState(true)
  const [telaCheia, setTelaCheia] = useState(false)
  const [gravando, setGravando] = useState(false)

  const jaSorteado = palco?.sorteio.situacao === 'sorteado' && palco.resultado !== null

  const limparTimers = () => {
    timers.current.forEach((t) => window.clearTimeout(t))
    timers.current = []
  }
  const depois = (ms: number, fn: () => void) => {
    timers.current.push(window.setTimeout(fn, ms))
  }

  useEffect(() => () => {
    limparTimers()
    pararConfete.current?.()
    pararGravacao.current?.()
    sons.current?.fechar()
  }, [])

  // Esc fecha só fora da cena: no meio da roleta seria interromper o evento.
  useEffect(() => {
    const tecla = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && (fase === 'preparo' || fase === 'revelado') && !document.fullscreenElement) onFechar()
    }
    const mudouTela = () => setTelaCheia(!!document.fullscreenElement)
    window.addEventListener('keydown', tecla)
    document.addEventListener('fullscreenchange', mudouTela)
    return () => {
      window.removeEventListener('keydown', tecla)
      document.removeEventListener('fullscreenchange', mudouTela)
    }
  }, [fase, onFechar])

  const revelar = useCallback((r: ResultadoSorteio) => {
    setFase('revelado')
    sons.current?.fanfarra()
    if (canvas.current) {
      pararConfete.current?.()
      pararConfete.current = soltarConfete(canvas.current, CORES_CONFETE)
      // Segunda rajada: a comemoração não acaba no primeiro segundo.
      depois(1400, () => {
        if (canvas.current) pararConfete.current = soltarConfete(canvas.current, CORES_CONFETE, 160)
      })
    }
    setResultado(r)
  }, [])

  const girar = useCallback((r: ResultadoSorteio) => {
    if (!palco) return
    setFase('girando')
    const ganhador = { numero: r.numero, nome: r.cliente?.nome.split(' ')[0] ?? null }
    const roteiro = montarRoteiro(palco.participantes, ganhador)
    setUltimo(roteiro.length - 1)
    let t = 0
    roteiro.forEach((q, k) => {
      depois(t, () => {
        setQuadro(q)
        setIndice(k)
        sons.current?.tique()
      })
      t += q.duracao
    })
    // Pausa com o número travado antes de abrir a revelação — o "é este!".
    depois(t + 900, () => revelar(r))
  }, [palco, revelar])

  async function iniciar(reapresentar: boolean) {
    if (!palco) return
    setErro(null)
    limparTimers()
    pararConfete.current?.()
    setResultado(null)
    setQuadro(null)
    sons.current?.fechar()
    sons.current = criarSons(comSom)

    // O resultado é decidido JÁ, em paralelo com a contagem: a cena que vem
    // depois não pode influenciar quem ganha.
    // Na reapresentação logo depois de sortear, o palco pode ainda não ter
    // recarregado: o resultado em memória vale, e NUNCA se sorteia de novo.
    const anterior = palco.resultado ?? resultado
    if (reapresentar && !anterior) return
    const pedido: Promise<ResultadoSorteio> = reapresentar && anterior
      ? Promise.resolve(anterior)
      : sortear.mutateAsync(palco.sorteio.id)

    setFase('contagem')
    for (let n = CONTAGEM; n >= 0; n--) {
      depois((CONTAGEM - n) * 1000, () => {
        setContagem(n)
        sons.current?.bip(n === 0)
      })
    }

    try {
      const r = await pedido
      // Respeita a contagem inteira mesmo que o servidor responda antes.
      depois(CONTAGEM * 1000 + 900, () => girar(r))
    } catch (e: unknown) {
      limparTimers()
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
      setErro(msg ?? 'Não foi possível realizar o sorteio. Nada foi registrado.')
      setFase('preparo')
    }
  }

  async function alternarTelaCheia() {
    try {
      if (document.fullscreenElement) await document.exitFullscreen()
      else await raiz.current?.requestFullscreen()
    } catch {
      /* navegador recusou (iframe/iOS): o palco já ocupa a janela inteira */
    }
  }

  async function alternarGravacao() {
    if (gravando) {
      pararGravacao.current?.()
      return
    }
    try {
      const nome = `sorteio-${sorteioId}-${new Date().toISOString().slice(0, 10)}.webm`
      pararGravacao.current = await iniciarGravacao(nome, () => {
        setGravando(false)
        pararGravacao.current = null
      })
      setGravando(true)
    } catch (e: unknown) {
      // Cancelar a escolha da tela não é erro do sorteio.
      if ((e as Error)?.name !== 'NotAllowedError') setErro((e as Error)?.message ?? 'Não foi possível gravar.')
    }
  }

  const cliente = resultado?.cliente ?? null
  const local = [cliente?.bairro, cliente?.cidade && `${cliente.cidade}${cliente.uf ? `/${cliente.uf}` : ''}`].filter(Boolean).join(' · ')

  return (
    <div ref={raiz} className="palco" data-fase={fase} role="dialog" aria-modal="true" aria-label="Palco do sorteio">
      <canvas ref={canvas} aria-hidden="true" />

      <header className="palco-topo">
        <div className="palco-marca"><i aria-hidden="true" /> Sorteio{palco ? ` #${palco.sorteio.id}` : ''}</div>
        <div className="palco-acoes">
          <span className={`palco-aovivo${gravando ? ' gravando' : ''}`}>{gravando ? <><b aria-hidden="true" /> Gravando</> : 'Ao vivo'}</span>
          <button type="button" className="palco-btn" onClick={() => setComSom((s) => !s)} aria-pressed={comSom}>
            {comSom ? <Volume2 /> : <VolumeX />} {comSom ? 'Som' : 'Mudo'}
          </button>
          <button type="button" className="palco-btn" onClick={() => void alternarGravacao()}>
            {gravando ? <Square /> : <Video />} {gravando ? 'Parar e baixar' : 'Gravar'}
          </button>
          <button type="button" className="palco-btn" onClick={() => void alternarTelaCheia()}>
            {telaCheia ? <Minimize /> : <Maximize />} {telaCheia ? 'Sair da tela cheia' : 'Tela cheia'}
          </button>
          <button type="button" className="palco-btn" onClick={onFechar} aria-label="Fechar o palco"><X /></button>
        </div>
      </header>

      <main className="palco-centro" aria-live="polite">
        {isLoading && <p className="palco-numeros">Preparando o palco…</p>}
        {error && <p className="palco-erro">Não foi possível carregar o sorteio.</p>}

        {palco && fase === 'preparo' && (
          <>
            <span className="palco-rotulo">Sorteio oficial</span>
            <h1 className="palco-titulo">{palco.sorteio.descricao}</h1>
            {palco.sorteio.premio && <div className="palco-premio"><Gift aria-hidden="true" /> {palco.sorteio.premio}</div>}
            <p className="palco-numeros">
              <b>{palco.participantes.length.toLocaleString('pt-BR')}</b> {palco.participantes.length === 1 ? 'número participando' : 'números participando'}
            </p>
            {jaSorteado ? (
              <>
                <button type="button" className="palco-iniciar" onClick={() => void iniciar(true)}><RotateCcw /> Reapresentar resultado</button>
                <p className="palco-aviso">Este sorteio já foi realizado em {dataHora(palco.resultado!.sorteado_em)}. A reapresentação mostra o mesmo ganhador — ideal para gravar de novo.</p>
              </>
            ) : (
              <>
                <button type="button" className="palco-iniciar" disabled={palco.participantes.length === 0 || sortear.isPending} onClick={() => void iniciar(false)}>
                  <Play /> Iniciar sorteio
                </button>
                <p className="palco-aviso">
                  {palco.participantes.length === 0
                    ? 'Adicione números antes de sortear.'
                    : 'O resultado é definitivo e fica registrado com data, hora e quem conduziu. Para gravar, clique em “Gravar” antes de iniciar.'}
                </p>
              </>
            )}
            {erro && <p className="palco-erro" role="alert">{erro}</p>}
          </>
        )}

        {fase === 'contagem' && (
          <div className="palco-contagem" aria-label={contagem > 0 ? `Faltam ${contagem}` : 'Valendo'}>
            <svg viewBox="0 0 100 100" aria-hidden="true">
              <circle className="trilho" cx="50" cy="50" r="46" />
              {contagem > 0 && <circle key={contagem} className="anel" cx="50" cy="50" r="46" />}
            </svg>
            <span key={contagem} className={`palco-digito${contagem === 0 ? ' valendo' : ''}`}>{contagem > 0 ? contagem : 'Valendo!'}</span>
          </div>
        )}

        {fase === 'girando' && (
          <>
            <span className="palco-rotulo">Sorteando</span>
            <div className="palco-janela">
              {quadro && (
                <div
                  key={indice}
                  className={`palco-giro troca${indice === ultimo ? ' parou' : ''}`}
                  style={{ ['--dur' as string]: `${Math.min(quadro.duracao, 220)}ms` }}
                >
                  <span className="num">{quadro.numero}</span>
                  <span className="nome">{quadro.nome ?? ' '}</span>
                </div>
              )}
            </div>
          </>
        )}

        {fase === 'revelado' && resultado && (
          <div className="palco-revelado">
            <Trophy className="palco-trofeu" aria-hidden="true" />
            <span className="palco-rotulo">Temos um ganhador!</span>
            <div className="palco-bilhete"><small>Nº</small>{resultado.numero}</div>
            <h1 className="palco-ganhador">{cliente?.nome ?? 'Número sem cliente vinculado'}</h1>
            {(local || cliente?.telefone) && (
              <div className="palco-local">
                {local && <span><MapPin aria-hidden="true" /> {local}</span>}
                {cliente?.telefone && <span><Phone aria-hidden="true" /> {cliente.telefone}</span>}
              </div>
            )}
            {palco?.sorteio.premio && <div className="palco-premio"><Gift aria-hidden="true" /> {palco.sorteio.premio}</div>}
          </div>
        )}
      </main>

      <footer className="palco-rodape">
        <span className="palco-registro">
          {resultado
            ? <>Sorteado em <b>{dataHora(resultado.sorteado_em)}</b> · entre <b>{resultado.participantes.toLocaleString('pt-BR')}</b> números{resultado.sorteado_por ? <> · conduzido por <b>{resultado.sorteado_por}</b></> : null}</>
            : palco?.sorteio.data_sorteio
              ? <>Data do sorteio: <b>{new Date(`${palco.sorteio.data_sorteio}T12:00:00`).toLocaleDateString('pt-BR')}</b></>
              : ' '}
        </span>
        {fase === 'revelado' && (
          <div className="palco-acoes">
            <button type="button" className="palco-btn" onClick={() => void iniciar(true)}><RotateCcw /> Reapresentar</button>
            <button type="button" className="palco-btn" onClick={onFechar}><X /> Fechar</button>
          </div>
        )}
      </footer>
    </div>
  )
}
