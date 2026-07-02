import MissaoService from "@/services/missao.service"
import * as Location from "expo-location"
import { useEffect, useRef } from "react"

const CAPTURA_MS = 30000 // captura um ponto a cada 30s
const LOTE = 5 // envia ao acumular 5 pontos

/**
 * useTrilhaMissao (L7) — enquanto a missão está EM ANDAMENTO, captura a posição
 * periodicamente e envia em LOTE ao ERP (percurso/distância/tempo por casa na
 * auditoria). Acumula localmente e descarrega a cada N pontos; o flush final
 * acontece no próximo lote (perda máxima aceitável: < N pontos).
 */
export function useTrilhaMissao(emAndamento: boolean) {
    const buffer = useRef<{ latitude: number; longitude: number; registrado_em: string }[]>([])
    const timer = useRef<ReturnType<typeof setInterval> | null>(null)

    useEffect(() => {
        if (!emAndamento) {
            if (timer.current) {
                clearInterval(timer.current)
                timer.current = null
            }
            return
        }

        const capturar = async () => {
            try {
                const pos = await Location.getCurrentPositionAsync({
                    accuracy: Location.Accuracy.Balanced,
                })
                buffer.current.push({
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    registrado_em: new Date().toISOString(),
                })
                if (buffer.current.length >= LOTE) {
                    const lote = buffer.current.splice(0, buffer.current.length)
                    await MissaoService.Trilha(lote).catch(() => {
                        // devolve ao buffer em caso de falha de rede (tenta no próximo)
                        buffer.current.unshift(...lote)
                    })
                }
            } catch {
                // GPS indisponível neste tick — tenta no próximo.
            }
        }

        capturar()
        timer.current = setInterval(capturar, CAPTURA_MS)

        return () => {
            if (timer.current) {
                clearInterval(timer.current)
                timer.current = null
            }
        }
    }, [emAndamento])
}
