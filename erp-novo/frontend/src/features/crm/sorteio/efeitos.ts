/**
 * Som e confete do palco, sem dependência nova.
 *
 * Som por Web Audio sintetizado (sem arquivo): o contexto só pode nascer de um
 * clique do usuário — é a regra de autoplay dos navegadores —, por isso
 * `criarSons()` é chamado no botão "Iniciar". Se o navegador recusar, o
 * sorteio segue mudo: som é enfeite, não pode derrubar o evento.
 */
export interface Sons {
  bip(final?: boolean): void
  tique(): void
  fanfarra(): void
  fechar(): void
}

const MUDO: Sons = { bip() {}, tique() {}, fanfarra() {}, fechar() {} }

export function criarSons(ligado: boolean): Sons {
  if (!ligado) return MUDO
  const Ctx = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext
  if (!Ctx) return MUDO
  let ctx: AudioContext
  try {
    ctx = new Ctx()
  } catch {
    return MUDO
  }

  const nota = (freq: number, inicio: number, dur: number, tipo: OscillatorType = 'sine', volume = 0.18) => {
    const t = ctx.currentTime + inicio
    const osc = ctx.createOscillator()
    const ganho = ctx.createGain()
    osc.type = tipo
    osc.frequency.setValueAtTime(freq, t)
    ganho.gain.setValueAtTime(0.0001, t)
    ganho.gain.exponentialRampToValueAtTime(volume, t + 0.01)
    ganho.gain.exponentialRampToValueAtTime(0.0001, t + dur)
    osc.connect(ganho).connect(ctx.destination)
    osc.start(t)
    osc.stop(t + dur + 0.02)
  }

  return {
    bip: (final = false) => nota(final ? 1046 : 660, 0, final ? 0.6 : 0.18, 'sine', 0.22),
    tique: () => nota(1800, 0, 0.03, 'square', 0.04),
    fanfarra: () => {
      // Acorde maior subindo: dó-mi-sol-dó, e o acorde inteiro sustentado.
      ;[523, 659, 784, 1046].forEach((f, i) => nota(f, i * 0.12, 0.35, 'triangle', 0.2))
      ;[523, 659, 784, 1046].forEach((f) => nota(f, 0.55, 1.4, 'triangle', 0.12))
    },
    fechar: () => {
      void ctx.close().catch(() => {})
    },
  }
}

/**
 * Chuva de confete num canvas. Devolve a função que para a animação — o
 * componente chama ao desmontar, senão o `requestAnimationFrame` segue rodando
 * com o palco fechado.
 */
export function soltarConfete(canvas: HTMLCanvasElement, cores: string[], quantidade = 220): () => void {
  const c = canvas.getContext('2d')
  if (!c) return () => {}

  const ajustar = () => {
    canvas.width = canvas.clientWidth * devicePixelRatio
    canvas.height = canvas.clientHeight * devicePixelRatio
  }
  ajustar()
  const W = () => canvas.width
  const H = () => canvas.height

  const pecas = Array.from({ length: quantidade }, (_, i) => {
    // Duas rajadas, dos cantos de baixo para o centro — como canhões de festa.
    const esquerda = i % 2 === 0
    return {
      x: esquerda ? W() * 0.05 : W() * 0.95,
      y: H() * 0.95,
      vx: (esquerda ? 1 : -1) * (6 + Math.random() * 10) * devicePixelRatio,
      vy: -(14 + Math.random() * 14) * devicePixelRatio,
      giro: Math.random() * Math.PI,
      vgiro: (Math.random() - 0.5) * 0.3,
      larg: (6 + Math.random() * 8) * devicePixelRatio,
      alt: (10 + Math.random() * 10) * devicePixelRatio,
      cor: cores[i % cores.length],
      atraso: Math.random() * 25,
    }
  })

  let quadro = 0
  let id = 0
  const gravidade = 0.35 * devicePixelRatio
  const passo = () => {
    quadro++
    c.clearRect(0, 0, W(), H())
    let vivas = 0
    for (const p of pecas) {
      if (quadro < p.atraso) {
        vivas++
        continue
      }
      p.vy += gravidade
      p.vx *= 0.99
      p.x += p.vx
      p.y += p.vy
      p.giro += p.vgiro
      if (p.y < H() + 40) vivas++
      c.save()
      c.translate(p.x, p.y)
      c.rotate(p.giro)
      c.fillStyle = p.cor
      // Achatamento oscilante: o papel "vira" no ar.
      c.fillRect(-p.larg / 2, -p.alt / 2, p.larg, p.alt * Math.abs(Math.cos(p.giro * 2)))
      c.restore()
    }
    if (vivas > 0) id = requestAnimationFrame(passo)
  }
  id = requestAnimationFrame(passo)
  window.addEventListener('resize', ajustar)

  return () => {
    cancelAnimationFrame(id)
    window.removeEventListener('resize', ajustar)
    c.clearRect(0, 0, W(), H())
  }
}

/**
 * Grava a aba em vídeo (.webm) pelo próprio navegador.
 *
 * `getDisplayMedia` pede ao usuário qual tela/aba compartilhar — não há como
 * gravar sem essa permissão, e é bom que não haja. Marcar "compartilhar áudio
 * da aba" leva os sons junto.
 */
export async function iniciarGravacao(nomeArquivo: string, aoParar: () => void): Promise<() => void> {
  const md = navigator.mediaDevices as MediaDevices & { getDisplayMedia?: MediaDevices['getDisplayMedia'] }
  if (!md?.getDisplayMedia || typeof MediaRecorder === 'undefined') {
    throw new Error('Este navegador não grava a tela. Use o Chrome ou o Edge no computador, ou o gravador do sistema.')
  }
  const fluxo = await md.getDisplayMedia({
    video: { frameRate: 30 },
    audio: true,
    // Chrome: sugere a própria aba do palco.
    ...({ preferCurrentTab: true, selfBrowserSurface: 'include' } as object),
  } as DisplayMediaStreamOptions)

  const tipo = ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'].find((t) => MediaRecorder.isTypeSupported(t))
  const gravador = new MediaRecorder(fluxo, tipo ? { mimeType: tipo } : undefined)
  const partes: Blob[] = []
  gravador.ondataavailable = (e) => {
    if (e.data.size > 0) partes.push(e.data)
  }
  gravador.onstop = () => {
    fluxo.getTracks().forEach((t) => t.stop())
    const url = URL.createObjectURL(new Blob(partes, { type: 'video/webm' }))
    const a = document.createElement('a')
    a.href = url
    a.download = nomeArquivo
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 10_000)
    aoParar()
  }
  // Se o usuário encerrar pelo botão do navegador ("Parar compartilhamento").
  fluxo.getVideoTracks()[0]?.addEventListener('ended', () => {
    if (gravador.state !== 'inactive') gravador.stop()
  })
  gravador.start(1000)

  return () => {
    if (gravador.state !== 'inactive') gravador.stop()
  }
}
