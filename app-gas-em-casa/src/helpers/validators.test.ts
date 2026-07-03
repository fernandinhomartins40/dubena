import { describe, it, expect } from "vitest"
import { validateCpf, validateBirthDate } from "./validators"

/** M-5 — validadores puros do app do consumidor. */
describe("validateCpf", () => {
    it("aceita CPF válido (com e sem máscara)", () => {
        expect(validateCpf("529.982.247-25")).toBe(true)
        expect(validateCpf("52998224725")).toBe(true)
    })

    it("rejeita dígito verificador errado", () => {
        expect(validateCpf("529.982.247-24")).toBe(false)
    })

    it("rejeita sequências repetidas (todas — não só 00000000000)", () => {
        expect(validateCpf("00000000000")).toBe(false)
        expect(validateCpf("11111111111")).toBe(false)
        expect(validateCpf("99999999999")).toBe(false)
    })

    it("rejeita tamanho inválido", () => {
        expect(validateCpf("123")).toBe(false)
        expect(validateCpf("")).toBe(false)
    })
})

describe("validateBirthDate", () => {
    it("vazio é válido (campo opcional)", () => {
        expect(validateBirthDate("").isValid).toBe(true)
    })

    it("aceita data plausível dd/MM/AAAA", () => {
        expect(validateBirthDate("15/05/1990").isValid).toBe(true)
    })

    it("rejeita mês e dia inválidos", () => {
        expect(validateBirthDate("15/13/1990")).toMatchObject({ isValid: false, message: "mês inválido" })
        expect(validateBirthDate("32/01/1990")).toMatchObject({ isValid: false, message: "dia inválido" })
    })

    it("rejeita 29/02 em ano não bissexto", () => {
        expect(validateBirthDate("29/02/1990").isValid).toBe(false)
    })

    it("rejeita formato fora de dd/MM/AAAA", () => {
        expect(validateBirthDate("1990-05-15").isValid).toBe(false)
    })
})
