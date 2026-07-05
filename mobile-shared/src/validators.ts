/**
 * Validadores PUROS (M-5) — sem dependência de React Native, store ou libs de UI,
 * para poderem ser testados (Vitest, ambiente node) e reusados sem arrastar o
 * runtime do app. Extraídos de utils.ts, que os re-exporta para não quebrar os
 * imports existentes.
 */

/** Valida um CPF (com ou sem máscara). Dígitos verificadores + rejeita repetido. */
export const validateCpf = (cpf: string): boolean => {
    let sum = 0
    let rest = 0
    const strCpf = (cpf ?? "").replace(/\./g, "").replace(/-/g, "")

    if (strCpf.length !== 11) return false
    // Rejeita sequências repetidas (00000000000, 11111111111, …) — todas passariam
    // no cálculo dos dígitos, mas são CPFs inválidos.
    if (/^(\d)\1{10}$/.test(strCpf)) return false

    for (let i = 1; i <= 9; i++) {
        sum += parseInt(strCpf.substring(i - 1, i)) * (11 - i)
    }
    rest = (sum * 10) % 11
    if (rest === 10 || rest === 11) rest = 0
    if (rest !== parseInt(strCpf.substring(9, 10))) return false

    sum = 0
    for (let i = 1; i <= 10; i++) {
        sum += parseInt(strCpf.substring(i - 1, i)) * (12 - i)
    }
    rest = (sum * 10) % 11
    if (rest === 10 || rest === 11) rest = 0
    if (rest !== parseInt(strCpf.substring(10, 11))) return false

    return true
}

/** Valida uma data de nascimento no formato dd/MM/AAAA (vazio = válido/opcional). */
export const validateBirthDate = (date: string): { isValid: boolean; message: string } => {
    if (date === "" || date === null) {
        return { isValid: true, message: "" }
    }

    let message = "padrão aceito é dd/MM/AAAA"
    let isValid = !!date && date.length === 10

    if (isValid) {
        try {
            const valYear = new Date().getFullYear() - 3
            const dateS = date.split("/")
            const day = parseInt(dateS[0])
            const month = parseInt(dateS[1])
            const year = parseInt(dateS[2])

            if (year > valYear || year < 1900) {
                message = "ano inválido"
                isValid = false
            } else if (month === 0 || month > 12) {
                message = "mês inválido"
                isValid = false
            } else {
                let maxDay = 30
                switch (month) {
                    case 1:
                    case 3:
                    case 5:
                    case 7:
                    case 8:
                    case 10:
                    case 12:
                        maxDay = 31
                        break
                    case 2:
                        maxDay = year % 4 === 0 ? 29 : 28
                }
                if (maxDay < day || day === 0 || isNaN(day)) {
                    message = "dia inválido"
                    isValid = false
                }
            }
        } catch (e) {
            isValid = false
        }
    }

    return { isValid, message }
}
