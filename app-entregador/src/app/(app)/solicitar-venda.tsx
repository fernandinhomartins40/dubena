import { Botao, Campo, Cartao } from "@/components/ui"
import { COLORS } from "@/constants/app"
import MissaoService from "@/services/missao.service"
import VendaService from "@/services/venda.service"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { router, useLocalSearchParams } from "expo-router"
import { Check, Minus, Plus, Send } from "lucide-react-native"
import { useState } from "react"
import { ScrollView, StyleSheet, Text, TouchableOpacity, View } from "react-native"
import Toast from "react-native-toast-message"

const brl = (v: number) => `R$ ${v.toFixed(2).replace(".", ",")}`

/**
 * Solicitar venda à Central (F4) — o caminho do franqueado e do industrial.
 *
 * A regra do negócio é que o franqueado **não fecha o pedido**: ele monta a
 * proposta e pede; a central de vendas cria, aprova o desconto e fatura.
 *
 * **O preço é do servidor.** A tela soma o preço de tabela só para o vendedor se
 * situar — o valor que vale é o que a Central apurar. É o oposto do legado, onde
 * o campo de preço era livre (PedidoFragment2.java:80) e o backend aceitava o que
 * o app mandasse (MobileRepository::getPreco:602).
 *
 * **Funciona sem sinal:** a solicitação passa pela fila offline. Na porta do
 * cliente, depender de rede para registrar o pedido é o que faz o vendedor
 * anotar no papel — e o papel não chega à Central.
 */
export default function SolicitarVenda() {
    const params = useLocalSearchParams<{ cliente_id?: string; nome?: string }>()
    const qc = useQueryClient()

    const [clienteId] = useState<number | null>(params.cliente_id ? Number(params.cliente_id) : null)
    const [qtd, setQtd] = useState<Record<number, number>>({})
    const [desconto, setDesconto] = useState("")
    const [justificativa, setJustificativa] = useState("")

    const { data: produtos } = useQuery({
        queryKey: ["entregador", "missao-produtos"],
        queryFn: MissaoService.Produtos,
    })

    const itens = Object.entries(qtd)
        .filter(([, q]) => q > 0)
        .map(([id, q]) => ({ produto_id: Number(id), quantidade: q }))

    const total = (produtos ?? []).reduce((acc, p) => acc + (qtd[p.id] ?? 0) * p.preco, 0)
    const descontoNum = Number(desconto.replace(",", ".")) || 0

    const enviar = useMutation({
        mutationFn: () =>
            VendaService.Solicitar({
                cliente_id: clienteId!,
                itens,
                desconto_solicitado: descontoNum,
                justificativa: justificativa || undefined,
            }),
        onSuccess: (r) => {
            qc.invalidateQueries({ queryKey: ["entregador", "solicitacoes"] })
            // A fila devolve `enfileirado: true` quando não havia rede — o
            // vendedor precisa saber que a Central ainda não viu o pedido.
            Toast.show({
                type: "success",
                text1: r.enfileirado ? "Guardado" : "Enviado à Central",
                text2: r.enfileirado
                    ? "Sem sinal agora. Vai assim que a rede voltar."
                    : "A central de vendas vai analisar.",
            })
            router.back()
        },
        onError: (e: any) => {
            Toast.show({
                type: "error",
                text1: "Não foi possível enviar",
                text2: e?.message ?? "Tente novamente.",
            })
        },
    })

    const podeEnviar = clienteId !== null && itens.length > 0 && !enviar.isPending

    return (
        <ScrollView style={s.tela} contentContainerStyle={{ padding: 16, gap: 12 }}>
            <Cartao>
                <Text style={s.titulo}>Cliente</Text>
                <Text style={s.cliente}>{params.nome ?? "Selecione um cliente"}</Text>
                {clienteId === null && (
                    <Text style={s.aviso}>
                        Abra esta tela a partir de um cliente para solicitar a venda.
                    </Text>
                )}
            </Cartao>

            <Cartao>
                <Text style={s.titulo}>Produtos</Text>
                {(produtos ?? []).map((p) => (
                    <View key={p.id} style={s.linha}>
                        <View style={{ flex: 1 }}>
                            <Text style={s.produto}>{p.descricao}</Text>
                            <Text style={s.preco}>{brl(p.preco)}</Text>
                        </View>
                        <View style={s.contador}>
                            <TouchableOpacity
                                onPress={() => setQtd((q) => ({ ...q, [p.id]: Math.max((q[p.id] ?? 0) - 1, 0) }))}
                                style={s.botaoQtd}
                            >
                                <Minus size={16} color={COLORS.text} />
                            </TouchableOpacity>
                            <Text style={s.qtd}>{qtd[p.id] ?? 0}</Text>
                            <TouchableOpacity
                                onPress={() => setQtd((q) => ({ ...q, [p.id]: (q[p.id] ?? 0) + 1 }))}
                                style={s.botaoQtd}
                            >
                                <Plus size={16} color={COLORS.text} />
                            </TouchableOpacity>
                        </View>
                    </View>
                ))}
            </Cartao>

            <Cartao>
                <Text style={s.titulo}>Desconto solicitado</Text>
                <Campo
                    label="Valor em R$ (deixe vazio se não houver)"
                    value={desconto}
                    onChangeText={setDesconto}
                    keyboardType="decimal-pad"
                    placeholder="0,00"
                />
                {descontoNum > 0 && (
                    <Campo
                        label="Por quê? (a Central lê isto para decidir)"
                        value={justificativa}
                        onChangeText={setJustificativa}
                        placeholder="Ex.: cliente fechou com concorrente por menos"
                        multiline
                    />
                )}
            </Cartao>

            <Cartao>
                <View style={s.totalLinha}>
                    <Text style={s.totalRotulo}>Total de tabela</Text>
                    <Text style={s.totalValor}>{brl(total)}</Text>
                </View>
                {descontoNum > 0 && (
                    <View style={s.totalLinha}>
                        <Text style={s.totalRotulo}>Com o desconto pedido</Text>
                        <Text style={s.totalValor}>{brl(Math.max(total - descontoNum, 0))}</Text>
                    </View>
                )}
                <Text style={s.aviso}>
                    Valor final é o que a Central aprovar.
                </Text>
            </Cartao>

            <Botao
                titulo="Enviar à Central"
                onPress={() => enviar.mutate()}
                carregando={enviar.isPending}
                desabilitado={!podeEnviar}
            />
        </ScrollView>
    )
}

const s = StyleSheet.create({
    tela: { flex: 1, backgroundColor: COLORS.bg },
    titulo: { fontSize: 13, fontWeight: "700", color: COLORS.muted, marginBottom: 8 },
    cliente: { fontSize: 16, fontWeight: "600", color: COLORS.text },
    aviso: { fontSize: 12, color: COLORS.muted, marginTop: 6 },
    linha: { flexDirection: "row", alignItems: "center", paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: COLORS.border },
    produto: { fontSize: 15, color: COLORS.text },
    preco: { fontSize: 12, color: COLORS.muted },
    contador: { flexDirection: "row", alignItems: "center", gap: 12 },
    botaoQtd: { padding: 6, borderRadius: 6, backgroundColor: COLORS.bg },
    qtd: { minWidth: 22, textAlign: "center", fontSize: 16, fontWeight: "700", color: COLORS.text },
    totalLinha: { flexDirection: "row", justifyContent: "space-between", paddingVertical: 4 },
    totalRotulo: { fontSize: 14, color: COLORS.muted },
    totalValor: { fontSize: 16, fontWeight: "700", color: COLORS.text },
})
