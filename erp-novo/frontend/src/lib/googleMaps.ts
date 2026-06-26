/**
 * Carregador sob demanda do Google Maps JS SDK (com a lib `drawing` p/ cercas).
 * Carrega uma única vez por página; reusa a promessa. Sem dependência npm — usa
 * a key vinda da config global (mesma do legado ctrl-web).
 */
let promessa: Promise<typeof google> | null = null

export function carregarGoogleMaps(apiKey: string): Promise<typeof google> {
  if (typeof window !== 'undefined' && (window as any).google?.maps?.drawing) {
    return Promise.resolve((window as any).google)
  }
  if (promessa) return promessa

  promessa = new Promise((resolve, reject) => {
    const cb = '__initGmaps'
    ;(window as any)[cb] = () => resolve((window as any).google)
    const s = document.createElement('script')
    s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&libraries=drawing&callback=${cb}&language=pt-BR`
    s.async = true
    s.defer = true
    s.onerror = () => { promessa = null; reject(new Error('Falha ao carregar o Google Maps.')) }
    document.head.appendChild(s)
  })
  return promessa
}
