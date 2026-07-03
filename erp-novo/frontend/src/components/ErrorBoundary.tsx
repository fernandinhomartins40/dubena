import { Component, type ReactNode } from 'react'

/**
 * ErrorBoundary (FE-1 da auditoria) — impede que um erro de render em UMA página
 * derrube a SPA inteira ("tela branca"). Captura o erro, mostra um fallback com
 * a opção de recarregar, e isola a falha ao ramo envolvido.
 *
 * React só oferece error boundary via classe (não há hook equivalente). Envolve-se
 * o roteador com este componente; ao trocar de rota, `resetKey` reinicia o estado
 * para uma navegação limpa após o erro.
 */
interface Props {
  children: ReactNode
  /** Muda de valor a cada rota → reseta o boundary ao navegar. */
  resetKey?: string | number
  fallback?: ReactNode
}

interface State {
  erro: Error | null
}

export class ErrorBoundary extends Component<Props, State> {
  state: State = { erro: null }

  static getDerivedStateFromError(erro: Error): State {
    return { erro }
  }

  componentDidCatch(erro: Error, info: unknown) {
    // Log local (um coletor externo — Sentry etc. — entra aqui quando houver).
    // eslint-disable-next-line no-console
    console.error('[ErrorBoundary]', erro, info)
  }

  componentDidUpdate(prev: Props) {
    // Navegou para outra rota → limpa o erro e tenta renderizar a nova tela.
    if (this.state.erro && prev.resetKey !== this.props.resetKey) {
      this.setState({ erro: null })
    }
  }

  render() {
    if (this.state.erro) {
      if (this.props.fallback) return this.props.fallback
      return (
        <div className="h-full grid place-items-center p-8 text-center">
          <div className="max-w-md space-y-3">
            <h1 className="text-lg font-semibold">Algo deu errado nesta tela</h1>
            <p className="text-sm text-muted-foreground">
              Ocorreu um erro inesperado. Você pode voltar e tentar de novo — o resto do sistema segue funcionando.
            </p>
            <button
              className="mt-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
              onClick={() => window.location.reload()}
            >
              Recarregar
            </button>
          </div>
        </div>
      )
    }
    return this.props.children
  }
}
