/**
 * Conversão da data de nascimento entre a tela e a API.
 *
 * A tela usa a máscara DD/MM/AAAA; a API valida `nullable|date` e devolve ISO
 * (AAAA-MM-DD). Mandar o texto da máscara dava 422 em todo dia acima de 12 — e,
 * pior, um dia até 12 passava lido no formato americano: "05/06/1990" virava
 * 6 de maio, gravado sem erro nenhum.
 */

/** "15/05/1990" → "1990-05-15". Vazio ou fora do formato → null (o campo é opcional). */
export function dataBrParaIso(data: string | null | undefined): string | null {
    const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec((data ?? "").trim())

    return m ? `${m[3]}-${m[2]}-${m[1]}` : null
}

/** "1990-05-15" (ou com hora) → "15/05/1990"; o que não for ISO volta como veio. */
export function dataIsoParaBr(data: string | null | undefined): string {
    const valor = (data ?? "").trim()
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(valor)

    return m ? `${m[3]}/${m[2]}/${m[1]}` : valor
}
