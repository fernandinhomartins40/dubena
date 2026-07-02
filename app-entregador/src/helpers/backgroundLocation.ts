import EntregaService from "@/services/entrega.service"
import * as Location from "expo-location"
import * as TaskManager from "expo-task-manager"

export const TASK_POSICAO = "entregador-posicao-background"

/**
 * Rastreamento em SEGUNDO PLANO (F11) — o app parado no bolso continua enviando a
 * posição durante a jornada (Uber-like). Usa expo-task-manager + foreground
 * service no Android (notificação persistente exigida pelo SO).
 *
 * A task é definida no escopo de módulo (exigência do TaskManager: precisa ser
 * registrada no bundle antes do app montar — importada pelo _layout raiz).
 * Falhas de rede num tick são engolidas; o próximo tick tenta de novo.
 */
TaskManager.defineTask(TASK_POSICAO, async ({ data, error }) => {
    if (error || !data) return
    const { locations } = data as { locations: Location.LocationObject[] }
    const ultima = locations?.[locations.length - 1]
    if (!ultima) return

    try {
        await EntregaService.Posicao({
            latitude: ultima.coords.latitude,
            longitude: ultima.coords.longitude,
            velocidade: ultima.coords.speed ?? undefined,
            direcao: ultima.coords.heading != null ? Math.round(ultima.coords.heading) : undefined,
        })
    } catch {
        // sem rede/401 neste tick — o próximo tenta; nunca derruba a task.
    }
})

/**
 * Liga o rastreamento em background. Exige a permissão "sempre" (background);
 * retorna false se negada — o chamador cai para o modo foreground.
 */
export async function iniciarBackground(intervaloMs: number): Promise<boolean> {
    const fg = await Location.requestForegroundPermissionsAsync()
    if (!fg.granted) return false

    const bg = await Location.requestBackgroundPermissionsAsync()
    if (!bg.granted) return false

    const jaRodando = await Location.hasStartedLocationUpdatesAsync(TASK_POSICAO).catch(() => false)
    if (jaRodando) return true

    await Location.startLocationUpdatesAsync(TASK_POSICAO, {
        accuracy: Location.Accuracy.Balanced,
        timeInterval: intervaloMs,
        distanceInterval: 25, // ou a cada 25 m — o que vier primeiro
        showsBackgroundLocationIndicator: true, // iOS: indicador do SO
        foregroundService: {
            notificationTitle: "Em serviço",
            notificationBody: "Enviando sua posição para a central durante a jornada.",
            notificationColor: "#FF6200",
        },
    })

    return true
}

/** Desliga o rastreamento em background (fim da jornada/logout). */
export async function pararBackground(): Promise<void> {
    const rodando = await Location.hasStartedLocationUpdatesAsync(TASK_POSICAO).catch(() => false)
    if (rodando) {
        await Location.stopLocationUpdatesAsync(TASK_POSICAO).catch(() => {})
    }
}
