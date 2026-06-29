import { connectRealtime, realtimeDisponivel } from "@/helpers/realtime"
import { useEffect, useRef, useState } from "react"

/** Posição do entregador recebida em tempo real (P6). */
export interface PosicaoEntregador {
    lat: number
    lng: number
    velocidade?: number | null
    atualizadaEm: number
}

/** Atualização de situação do pedido recebida em tempo real (P5). */
export interface StatusPedido {
    situacao_id?: number | null
    situacao?: string | null
    efeito?: string | null
}

/**
 * useAcompanharPedido (P8) — assina os canais privados do pedido e devolve a
 * posição do entregador e a última mudança de situação em tempo real. Quando o
 * Reverb não está configurado, `aoVivo` é false e a tela mantém o polling.
 *
 * O callback `onStatus` permite à tela reagir (ex.: invalidar a query do pedido
 * para refletir o novo status sem esperar o próximo tick de polling).
 */
export function useAcompanharPedido(
    pedidoId: number | null | undefined,
    onStatus?: (s: StatusPedido) => void,
) {
    const [posicao, setPosicao] = useState<PosicaoEntregador | null>(null)
    const [aoVivo, setAoVivo] = useState(false)
    const onStatusRef = useRef(onStatus)
    onStatusRef.current = onStatus

    useEffect(() => {
        if (!pedidoId || !realtimeDisponivel()) {
            setAoVivo(false)
            return
        }

        const echo = connectRealtime()
        if (!echo) {
            setAoVivo(false)
            return
        }

        const canalPedido = `pedido.${pedidoId}`
        const canalPosicao = `pedido.${pedidoId}.entregador`
        setAoVivo(true)

        echo.private(canalPosicao).listen(".entregador.posicao", (e: any) => {
            if (typeof e?.lat === "number" && typeof e?.lng === "number") {
                setPosicao({
                    lat: e.lat,
                    lng: e.lng,
                    velocidade: e.velocidade ?? null,
                    atualizadaEm: Date.now(),
                })
            }
        })

        echo.private(canalPedido).listen(".pedido.status", (e: any) => {
            onStatusRef.current?.({
                situacao_id: e?.situacao_id ?? null,
                situacao: e?.situacao ?? null,
                efeito: e?.efeito ?? null,
            })
        })

        return () => {
            echo.leave(canalPosicao)
            echo.leave(canalPedido)
            setAoVivo(false)
        }
    }, [pedidoId])

    return { posicao, aoVivo }
}
