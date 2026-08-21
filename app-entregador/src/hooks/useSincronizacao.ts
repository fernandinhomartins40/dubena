import { useEffect, useState, useCallback, useRef } from "react"
import { AppState, type AppStateStatus } from "react-native"
import { sincronizarPendencias } from "@/helpers/envioResiliente"
import { quantidadePendente } from "@/helpers/filaOffline"

/**
 * Dispara a fila offline (F7) — a peça que faltava.
 *
 * A fila guardava as escritas sem sinal, mas `sincronizarPendencias()` não era
 * chamada por lugar nenhum: enchia e nunca esvaziava. Este hook liga os dois
 * momentos em que vale tentar de novo:
 *
 *  1. **volta ao primeiro plano** — o entregador saiu do túnel, abriu o app;
 *  2. **periodicamente** enquanto o app está aberto — a rede pode voltar sem
 *     que ele mexa em nada.
 *
 * **Intervalo de 60s, não menor.** Cada tentativa sem rede gasta bateria num
 * aparelho que precisa durar o turno inteiro; e a fila para na primeira falha de
 * transporte, então tentar de dez em dez segundos não adiantaria nada.
 *
 * **Nunca lança.** Falha de sincronização não pode derrubar a tela onde o
 * entregador está trabalhando — o item continua na fila para a próxima janela.
 */
export function useSincronizacao(ativo: boolean = true) {
    const [pendentes, setPendentes] = useState(0)
    const [sincronizando, setSincronizando] = useState(false)
    // Evita duas execuções simultâneas (foreground + timer no mesmo instante),
    // que gastariam duas tentativas do mesmo item.
    const emAndamento = useRef(false)

    const sincronizar = useCallback(async () => {
        if (!ativo || emAndamento.current) return

        emAndamento.current = true
        setSincronizando(true)
        try {
            const r = await sincronizarPendencias()
            setPendentes(r.restantes)
        } catch {
            setPendentes(quantidadePendente())
        } finally {
            emAndamento.current = false
            setSincronizando(false)
        }
    }, [ativo])

    useEffect(() => {
        if (!ativo) return

        setPendentes(quantidadePendente())
        void sincronizar()

        const sub = AppState.addEventListener("change", (estado: AppStateStatus) => {
            if (estado === "active") void sincronizar()
        })

        const timer = setInterval(() => void sincronizar(), 60_000)

        return () => {
            sub.remove()
            clearInterval(timer)
        }
    }, [ativo, sincronizar])

    return { pendentes, sincronizando, sincronizar }
}
