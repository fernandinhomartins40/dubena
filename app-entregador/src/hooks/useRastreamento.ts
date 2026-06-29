import { POSICAO_INTERVALO_MS } from "@/constants/app"
import EntregaService from "@/services/entrega.service"
import useAppStore from "@/store/appStore"
import * as Location from "expo-location"
import { useEffect, useRef, useState } from "react"

/**
 * useRastreamento (P6) — enquanto o entregador estiver "em serviço", obtém a
 * posição do GPS e a envia ao ERP-NOVO em intervalos regulares. O servidor
 * publica o ping nos pedidos ATIVOS (tempo real para o cliente acompanhar).
 *
 * A permissão é pedida sob demanda; se negada, o hook fica inerte (sem crashar).
 */
export function useRastreamento() {
    const emServico = useAppStore((s) => s.emServico)
    const [permitido, setPermitido] = useState<boolean | null>(null)
    const [ultima, setUltima] = useState<Location.LocationObjectCoords | null>(null)
    const timer = useRef<ReturnType<typeof setInterval> | null>(null)

    const pedirPermissao = async (): Promise<boolean> => {
        const { status } = await Location.requestForegroundPermissionsAsync()
        const ok = status === "granted"
        setPermitido(ok)
        return ok
    }

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

        const iniciar = async () => {
            if (!emServico) return
            const ok = permitido ?? (await pedirPermissao())
            if (!ok || !ativo) return

            await enviarUmaVez()
            timer.current = setInterval(enviarUmaVez, POSICAO_INTERVALO_MS)
        }

        iniciar()

        return () => {
            ativo = false
            if (timer.current) {
                clearInterval(timer.current)
                timer.current = null
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [emServico])

    return { permitido, ultima, pedirPermissao }
}
