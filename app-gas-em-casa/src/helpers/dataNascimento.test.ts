import { describe, expect, it } from "vitest"
import { dataBrParaIso, dataIsoParaBr } from "./dataNascimento"

describe("dataBrParaIso", () => {
    it("converte a máscara da tela para o ISO da API", () => {
        expect(dataBrParaIso("25/12/1990")).toBe("1990-12-25")
    })

    it("não troca dia por mês (o 05/06 é 5 de junho)", () => {
        expect(dataBrParaIso("05/06/1990")).toBe("1990-06-05")
    })

    it("vazio ou incompleto vira null — o campo é opcional", () => {
        expect(dataBrParaIso("")).toBeNull()
        expect(dataBrParaIso(null)).toBeNull()
        expect(dataBrParaIso("25/12/19")).toBeNull()
    })
})

describe("dataIsoParaBr", () => {
    it("mostra na máscara o ISO que o perfil devolve", () => {
        expect(dataIsoParaBr("1990-12-25")).toBe("25/12/1990")
        expect(dataIsoParaBr("1990-12-25T00:00:00.000000Z")).toBe("25/12/1990")
    })

    it("vazio continua vazio", () => {
        expect(dataIsoParaBr(null)).toBe("")
    })

    it("ida e volta preservam a data", () => {
        expect(dataIsoParaBr(dataBrParaIso("05/06/1990"))).toBe("05/06/1990")
    })
})
