import { COLORS } from "@/constants/app"
import { useRastreamento } from "@/hooks/useRastreamento"
import useAppStore from "@/store/appStore"
import { Redirect, Stack } from "expo-router"

/**
 * Grupo autenticado (P7/F10). Bloqueia sem token e dispara o rastreamento por GPS
 * (useRastreamento) — que só envia pings durante a jornada. As TABS são a área
 * principal; ficam empilhadas por cima só as telas de fluxo (iniciar jornada,
 * detalhe da entrega, visita/venda de missão), com header laranja da marca.
 */
export default function AppLayout() {
    const token = useAppStore((s) => s.apiToken)
    // Hook montado no topo do grupo: vive enquanto o entregador estiver logado.
    useRastreamento()

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
        </Stack>
    )
}
