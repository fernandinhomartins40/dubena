/**
 * Valor em reais no formato brasileiro: R$ 2.355,00.
 *
 * Substitui o `toFixed(2).replace(".", ",")` que estava copiado em nove telas e
 * não separava milhar ("R$ 2355,00"). Feito à mão, sem Intl: o Hermes de builds
 * antigos não garante os dados de locale pt-BR, e o valor aparece em dinheiro.
 */
export function formatarMoeda(valor: number | string | null | undefined): string {
    const n = Number(valor ?? 0)
    const seguro = Number.isFinite(n) ? n : 0
    const [inteiro, centavos] = Math.abs(seguro).toFixed(2).split(".")
    const comMilhar = inteiro.replace(/\B(?=(\d{3})+(?!\d))/g, ".")
    return `${seguro < 0 ? "-" : ""}R$ ${comMilhar},${centavos}`
}
