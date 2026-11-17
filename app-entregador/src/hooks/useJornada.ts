import JornadaService from "@/services/jornada.service"
import useAppStore from "@/store/appStore"
import { useQuery } from "@tanstack/react-query"
import { useEffect } from "react"

/**
 * useJornada (L4) — fonte de verdade do "em serviço": lê a jornada ativa do
 * servidor e sincroniza o flag `emServico` do store (que liga o rastreamento por
 * GPS). Antes, `emServico` era um toggle volátil e local; agora reflete a jornada.
 */
/**
 * Vigia da jornada no layout autenticado (P2-C-07). `useJornada` só era montado
 * no Início e no Perfil: se a Central encerrasse a jornada com o entregador em
 * outra tela, `emServico` seguia true e o GPS ligado. Mesma queryKey: o React
 * Query compartilha a consulta, não dobra a requisição.
 */
export function useVigiaJornada(ativo: boolean) {
    const setEmServico = useAppStore((s) => s.setEmServico)
    const jornada = useQuery({
        queryKey: ["entregador", "jornada"],
        queryFn: JornadaService.Atual,
        refetchInterval: 60000,
        enabled: ativo,
    })
    useEffect(() => {
        // Só a RESPOSTA do servidor decide; erro de rede mantém o estado.
        if (jornada.isSuccess) setEmServico(!!jornada.data)
    }, [jornada.isSuccess, jornada.data, setEmServico])
}

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
