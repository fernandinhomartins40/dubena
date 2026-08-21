import { COLORS } from "@/constants/app"
import { useRastreamento } from "@/hooks/useRastreamento"
import { useSincronizacao } from "@/hooks/useSincronizacao"
import useAppStore from "@/store/appStore"
import { Redirect, Stack } from "expo-router"

/**
 * Grupo autenticado (P7/F10). Bloqueia sem token e dispara o rastreamento por GPS
 * (useRastreamento) — que só envia pings durante a jornada. As TABS são a área
 * principal; ficam empilhadas por cima só as telas de fluxo (iniciar jornada,
 * detalhe da entrega, visita/venda de missão), com header laranja da marca.
 *
 * F7 — `useSincronizacao` fica aqui pelo mesmo motivo do rastreamento: precisa
 * viver enquanto o entregador estiver logado, independentemente da tela aberta.
 * Numa tela específica, sair dela pararia de esvaziar a fila.
 */
export default function AppLayout() {
    const token = useAppStore((s) => s.apiToken)
    // Hooks montados no topo do grupo: vivem enquanto o entregador estiver logado.
    useRastreamento()
    useSincronizacao(Boolean(token))

    if (!token) return <Redirect href="/login" />

    return (
        <Stack
            screenOptions={{
                headerStyle: { backgroundColor: COLORS.primary },
                headerTintColor: COLORS.white,
                headerTitleStyle: { fontWeight: "700" },
                contentStyle: { backgroundColor: COLORS.bg },
            }}
        >
            <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
            <Stack.Screen name="iniciar-jornada" options={{ title: "Iniciar jornada" }} />
            <Stack.Screen name="navegacao" options={{ headerShown: false }} />
            <Stack.Screen name="pedido/[id]/index" options={{ title: "Entrega" }} />
            <Stack.Screen name="pedido/[id]/ocorrencia" options={{ title: "Registrar ocorrência" }} />
            <Stack.Screen name="pedido/[id]/concluir" options={{ title: "Comprovar entrega" }} />
            <Stack.Screen name="missao-visita" options={{ title: "Registrar visita" }} />
            <Stack.Screen name="missao-venda" options={{ title: "Vender em campo" }} />
            {/* F4/F5 — telas do franqueado/industrial. Empilhadas e nao em TAB:
                a barra ja tem cinco areas, e uma sexta apertaria os rotulos. */}
            <Stack.Screen name="solicitar-venda" options={{ title: "Solicitar à Central" }} />
            <Stack.Screen name="ganhos" options={{ title: "Ganhos e mercadoria" }} />
            <Stack.Screen name="solicitacoes" options={{ title: "Minhas solicitações" }} />
            <Stack.Screen name="clientes" options={{ title: "Buscar cliente" }} />
            <Stack.Screen name="cliente-novo" options={{ title: "Novo cliente" }} />
            <Stack.Screen name="vale-gas" options={{ title: "Verificar Vale Gás" }} />
            <Stack.Screen name="relatorio-vendas" options={{ title: "Minhas vendas" }} />
        </Stack>
    )
}
