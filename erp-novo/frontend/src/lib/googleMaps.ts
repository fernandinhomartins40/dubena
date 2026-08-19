/**
 * Carregador sob demanda do Google Maps JS SDK.
 *
 * A lib `drawing` NÃO é mais carregada: a Google descontinuou o `DrawingManager`
 * na versão 3.65 da Maps JavaScript API, e o aviso vinha impresso sobre o mapa.
 * O desenho de cercas usa `Polygon` editável com clique no mapa — mesmo
 * resultado, sem dependência descontinuada.
 * Carrega uma única vez por página; reusa a promessa. Sem dependência npm — usa
 * a key vinda da config global (mesma do legado ctrl-web).
 *
 * Robusto: resolve quando google.maps existe; rejeita com mensagem útil em
 * onerror, em gm_authFailure (chave/restrição/billing inválidos) ou por timeout.
 */
let promessa: Promise<any> | null = null

export function carregarGoogleMaps(apiKey: string): Promise<any> {
  const w = window as any
  if (w.google?.maps?.Map) return Promise.resolve(w.google)
  if (promessa) return promessa

  promessa = new Promise((resolve, reject) => {
    const falhar = (msg: string) => { promessa = null; reject(new Error(msg)) }

    // O Google chama gm_authFailure() quando a CHAVE é inválida/restrita/sem billing.
    // Nesse caso o script carrega (onload), mas a API não funciona — capturamos aqui.
    w.gm_authFailure = () => falhar('Chave do Google Maps inválida ou não autorizada para este domínio (verifique restrições/billing no Google Cloud).')

    const cb = '__initGmaps_' + Date.now()
    w[cb] = () => {
      if (w.google?.maps) resolve(w.google)
      else falhar('Google Maps carregou sem a API esperada.')
    }

    const timeout = setTimeout(() => falhar('Tempo esgotado ao carregar o Google Maps.'), 15000)
    const limpar = () => clearTimeout(timeout)

    const s = document.createElement('script')
    s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&callback=${cb}&language=pt-BR&region=BR&loading=async`
    s.async = true
    s.defer = true
    s.onload = limpar
    s.onerror = () => { limpar(); falhar('Falha de rede ao carregar o Google Maps.') }
    document.head.appendChild(s)
  })
  return promessa
}
