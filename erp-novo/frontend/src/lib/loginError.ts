/** Mensagens de acesso não confundem indisponibilidade com credencial inválida. */
export function loginError(error: unknown): string {
  const response = (error as { response?: { status?: number; headers?: Record<string, unknown> } })?.response
  if (!response) return 'Não foi possível conectar. Verifique sua conexão e tente novamente.'
  if (response.status === 429) {
    const seconds = Number(response.headers?.['retry-after'])
    return Number.isFinite(seconds) && seconds > 0
      ? `Muitas tentativas. Tente novamente após ${Math.ceil(seconds)} segundos, conforme informado pelo servidor.`
      : 'Muitas tentativas. Aguarde e tente novamente.'
  }
  if (response.status === 401 || response.status === 422) return 'E-mail/usuário ou senha inválidos. Revise os dados e tente novamente.'
  if (response.status === 403) return 'Acesso não autorizado. Entre em contato com o administrador da sua organização.'
  if (response.status === 419) return 'A sessão de acesso expirou. Atualize a página e tente novamente.'
  return 'O serviço de acesso está indisponível. Tente novamente mais tarde.'
}
