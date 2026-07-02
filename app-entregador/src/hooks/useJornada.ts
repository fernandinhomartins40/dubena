import JornadaService from "@/services/jornada.service"
import useAppStore from "@/store/appStore"
import { useQuery } from "@tanstack/react-query"
import { useEffect } from "react"

/**
 * useJornada (L4) — fonte de verdade do "em serviço": lê a jornada ativa do
 * servidor e sincroniza o flag `emServico` do store (que liga o rastreamento por
 * GPS). Antes, `emServico` era um toggle volátil e local; agora reflete a jornada.
 */
export function useJornada() {
    const setEmServico = useAppStore((s) => s.setEmServico)

    const jornada = useQuery({
        queryKey: ["entregador", "jornada"],
        queryFn: JornadaService.Atual,
        refetchInterval: 60000,
    })

    const dashboard = useQuery({
        queryKey: ["entregador", "dashboard"],
        queryFn: JornadaService.Dashboard,
        refetchInterval: 30000,
    })

    // Liga/desliga o GPS conforme a jornada ativa.
    useEffect(() => {
        setEmServico(!!jornada.data)
    }, [jornada.data, setEmServico])

    return { jornada, dashboard, ativa: !!jornada.data }
}
