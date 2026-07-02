import { POSICAO_INTERVALO_MS } from "@/constants/app"
import { iniciarBackground, pararBackground } from "@/helpers/backgroundLocation"
import EntregaService from "@/services/entrega.service"
import useAppStore from "@/store/appStore"
import * as Location from "expo-location"
import { useEffect, useRef, useState } from "react"

/**
 * useRastreamento (P6/F11) — enquanto o entregador estiver em jornada
 * (`emServico`, sincronizado pelo useJornada), envia a posição ao ERP-NOVO.
 *
 * F11: tenta o modo BACKGROUND primeiro (expo-task-manager + foreground service —
 * o app no bolso continua rastreando). Se a permissão "sempre" for negada, cai
 * para o loop em foreground (comportamento anterior). Nada crasha se negado.
 */
export function useRastreamento() {
    const emServico = useAppStore((s) => s.emServico)
    const [permitido, setPermitido] = useState<boolean | null>(null)
    const [modo, setModo] = useState<"background" | "foreground" | null>(null)
    const [ultima, setUltima] = useState<Location.LocationObjectCoords | null>(null)
    const timer = useRef<ReturnType<typeof setInterval> | null>(null)

    const enviarUmaVez = async () => {
        try {
            const pos = await Location.getCurrentPositionAsync({
                accuracy: Location.Accuracy.Balanced,
            })
            setUltima(pos.coords)
            await EntregaService.Posicao({
                latitude: pos.coords.latitude,
                longitude: pos.coords.longitude,
                velocidade: pos.coords.speed ?? undefined,
                direcao: pos.coords.heading != null ? Math.round(pos.coords.heading) : undefined,
            })
        } catch {
            // Falha de GPS/rede num tick não derruba o loop; tenta de novo no próximo.
        }
    }

    useEffect(() => {
        let ativo = true

        const pararForeground = () => {
            if (timer.current) {
                clearInterval(timer.current)
                timer.current = null
            }
        }

        const iniciar = async () => {
            if (!emServico) {
                pararForeground()
                await pararBackground()
                return
            }

            // 1º: background (o SO acorda a task mesmo com o app fechado).
            const bgOk = await iniciarBackground(POSICAO_INTERVALO_MS).catch(() => false)
            if (!ativo) return
            if (bgOk) {
                setPermitido(true)
                setModo("background")
                pararForeground() // a task cuida de tudo
                return
            }

            // 2º: fallback foreground (permissão "sempre" negada).
            const fg = await Location.requestForegroundPermissionsAsync()
            const ok = fg.status === "granted"
            setPermitido(ok)
            if (!ok || !ativo) return
            setModo("foreground")

            await enviarUmaVez()
            timer.current = setInterval(enviarUmaVez, POSICAO_INTERVALO_MS)
        }

        iniciar()

        return () => {
            ativo = false
            pararForeground()
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [emServico])

    return { permitido, modo, ultima }
}
