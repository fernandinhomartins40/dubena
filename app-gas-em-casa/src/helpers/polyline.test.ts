import { describe, it, expect } from "vitest"
import { decodePolyline } from "./polyline"

/** M-5 — decodificação de polyline (algoritmo público do Google, precision 5). */
describe("decodePolyline", () => {
    it("decodifica o exemplo canônico do Google", () => {
        // "_p~iF~ps|U_ulLnnqC_mqNvxq`@" → 3 pontos conhecidos.
        const pts = decodePolyline("_p~iF~ps|U_ulLnnqC_mqNvxq`@")
        expect(pts).toHaveLength(3)
        expect(pts[0].latitude).toBeCloseTo(38.5, 5)
        expect(pts[0].longitude).toBeCloseTo(-120.2, 5)
        expect(pts[1].latitude).toBeCloseTo(40.7, 5)
        expect(pts[1].longitude).toBeCloseTo(-120.95, 5)
        expect(pts[2].latitude).toBeCloseTo(43.252, 5)
        expect(pts[2].longitude).toBeCloseTo(-126.453, 5)
    })

    it("string vazia → sem pontos", () => {
        expect(decodePolyline("")).toEqual([])
    })
})
