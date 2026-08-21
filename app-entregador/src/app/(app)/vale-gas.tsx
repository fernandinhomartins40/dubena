import { Botao, Campo, Cartao, Etiqueta } from "@/components/ui"
import { COLORS } from "@/constants/app"
import VendaService, { type ValeGasVerificado } from "@/services/venda.service"
import { fontSize } from "@/styles/theme"
import { useMutation } from "@tanstack/react-query"
import { CheckCircle2, Ticket, XCircle } from "lucide-react-native"
import { useState } from "react"
import { ScrollView, StyleSheet, Text, View } from "react-native"

const brl = (v: number) => `R$ ${Number(v).toFixed(2).replace(".", ",")}`

/**
 * Verificar Vale Gás — porta o `GasdeBolsoVerificacaoActivity` do MovelApp.
 *
 * O entregador digita (ou lê) o código e o servidor responde uma de três
 * recusas, na ordem do legado: não encontrado, cancelado, já utilizado.
 * "Cancelado" vem antes de "já utilizado" porque é a informação acionável — um
 * vale cancelado não vira venda de jeito nenhum.
 *
 * **Não passa pela fila offline, de propósito:** validar um vale sem consultar o
 * servidor seria aceitar um papel que pode já ter sido usado em outra entrega.
 * Aqui a resposta tem de ser do momento.
 */
export default function ValeGas() {
    const [codigo, setCodigo] = useState("")
    const [vale, setVale] = useState<ValeGasVerificado | null>(null)
    const [erro, setErro] = useState<string | null>(null)

    const verificar = useMutation({
        mutationFn: () => VendaService.VerificarValeGas(codigo.trim()),
        onSuccess: (r) => {
            setVale(r.data)
            setErro(null)
        },
        onError: (e: any) => {
            setVale(null)
            setErro(e?.message ?? "Não foi possível verificar o vale.")
        },
    })

    const limpar = () => {
        setCodigo("")
        setVale(null)
        setErro(null)
    }

    return (
        <ScrollView style={s.tela} contentContainerStyle={{ padding: 16, gap: 12 }} keyboardShouldPersistTaps="handled">
            <Cartao>
                <Campo
                    label="Código do vale"
                    value={codigo}
                    onChangeText={setCodigo}
                    placeholder="Digite ou leia o código de barras"
                    autoCapitalize="characters"
                    autoCorrect={false}
                    onSubmitEditing={() => codigo.trim() && verificar.mutate()}
                    returnKeyType="search"
                />
                <Botao
                    titulo="Verificar"
                    onPress={() => verificar.mutate()}
                    carregando={verificar.isPending}
                    desabilitado={codigo.trim() === "" || verificar.isPending}
                />
            </Cartao>

            {vale && (
                <Cartao style={{ gap: 10, borderColor: COLORS.success, borderWidth: 1.5 }}>
                    <View style={s.cabecalho}>
                        <CheckCircle2 size={22} color={COLORS.success} />
                        <Text style={s.valido}>Vale válido</Text>
                        <Etiqueta texto={brl(vale.valor)} />
                    </View>
                    <Text style={s.codigo}>{vale.codigo}</Text>
                    {vale.validade && <Text style={s.sub}>Validade: {vale.validade}</Text>}
                    <Botao titulo="Verificar outro" variante="secundario" onPress={limpar} />
                </Cartao>
            )}

            {erro && (
                <Cartao style={{ gap: 8, borderColor: COLORS.danger, borderWidth: 1.5 }}>
                    <View style={s.cabecalho}>
                        <XCircle size={22} color={COLORS.danger} />
                        <Text style={s.invalido}>{erro}</Text>
                    </View>
                    <Botao titulo="Tentar outro código" variante="secundario" onPress={limpar} />
                </Cartao>
            )}

            {!vale && !erro && (
                <Cartao style={{ alignItems: "center", gap: 8, paddingVertical: 28 }}>
                    <Ticket size={32} color={COLORS.muted} />
                    <Text style={s.sub}>Informe o código para verificar.</Text>
                </Cartao>
            )}
        </ScrollView>
    )
}

const s = StyleSheet.create({
    tela: { flex: 1, backgroundColor: COLORS.bg },
    cabecalho: { flexDirection: "row", alignItems: "center", gap: 8 },
    valido: { flex: 1, fontSize: fontSize.md, fontWeight: "800", color: COLORS.success },
    invalido: { flex: 1, fontSize: fontSize.md, fontWeight: "700", color: COLORS.danger },
    codigo: { fontSize: fontSize.lg, fontWeight: "800", color: COLORS.text, letterSpacing: 1 },
    sub: { fontSize: fontSize.sm, color: COLORS.muted },
})
