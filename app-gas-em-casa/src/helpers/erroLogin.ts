/**
 * P2-B-12 — o login por SMS engolia as falhas.
 *
 * Erro do Firebase ao enviar o SMS ia só para o `console.error` (a tela ficava
 * esperando um SMS que não vinha); qualquer erro do login diferente de 422
 * abria uma tela de erro sem botão. Aqui cada falha vira uma frase que diz o
 * que fazer, e o 422 só manda ao cadastro quando é REALMENTE "telefone não
 * encontrado" (`errors.cliente`) — antes qualquer 422 desviava para lá.
 */
export function mensagemErroSms(code: string | null | undefined): string {
    switch (code) {
        case "auth/invalid-phone-number":
        case "auth/missing-phone-number":
            return "Número de telefone inválido. Volte e confira o DDD e o número."
        case "auth/too-many-requests":
        case "auth/quota-exceeded":
            return "Muitas tentativas com este número. Aguarde alguns minutos e tente de novo."
        case "auth/network-request-failed":
            return "Sem conexão. Verifique sua internet e toque em Reenviar."
        case "auth/invalid-verification-code":
            return "Código incorreto. Confira o SMS e digite de novo."
        case "auth/code-expired":
        case "auth/session-expired":
            return "O código expirou. Toque em Reenviar para receber outro."
        case "auth/app-not-authorized":
        case "auth/missing-client-identifier":
        case "auth/captcha-check-failed":
            return "Não foi possível verificar este aparelho. Atualize o app ou tente mais tarde."
        default:
            return "Não foi possível enviar o SMS. Toque em Reenviar para tentar de novo."
    }
}

export type DesfechoErroLogin = { acao: "cadastro" } | { acao: "mensagem"; texto: string }

export function tratarErroLogin(err: { status?: number; message?: string; errors?: Record<string, unknown> } | null | undefined): DesfechoErroLogin {
    const status = err?.status ?? 0
    if (status === 422 && err?.errors && "cliente" in err.errors) return { acao: "cadastro" }
    if (status === 0) return { acao: "mensagem", texto: "Sem conexão. Verifique sua internet e tente de novo." }
    if (status === 401) return { acao: "mensagem", texto: "Não conseguimos confirmar seu telefone. Toque em Reenviar e digite o novo código." }
    if (status === 429) return { acao: "mensagem", texto: "Muitas tentativas. Aguarde um minuto e tente de novo." }
    if (status === 403) return { acao: "mensagem", texto: err?.message || "Esta revenda não está disponível no app no momento." }
    if (status >= 500) return { acao: "mensagem", texto: "O servidor da revenda não respondeu. Tente de novo em instantes." }
    return { acao: "mensagem", texto: err?.message || "Não foi possível entrar. Tente de novo." }
}
