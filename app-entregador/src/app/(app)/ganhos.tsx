import { Cartao, Etiqueta } from "@/components/ui"
import { COLORS } from "@/constants/app"
import { useSincronizacao } from "@/hooks/useSincronizacao"
import VendaService from "@/services/venda.service"
import { useQuery } from "@tanstack/react-query"
import { useState } from "react"
import { RefreshControl, ScrollView, StyleSheet, Text, View } from "react-native"

const brl = (v: number) => `R$ ${Number(v).toFixed(2).replace(".", ",")}`
const data = (iso: string) => new Date(iso).toLocaleDateString("pt-BR")

/**
 * Ganhos e mercadoria (F5) — a aba do franqueado.
 *
 * Duas perguntas que o franqueado faz todo dia e que o sistema não respondia:
 * *quanto eu ganhei* e *o que estou carregando*. Sem a primeira ele não confere
 * o acerto; sem a segunda, conta de cabeça e a divergência só aparece no fim do
 * turno, quando já não dá para reconstituir.
 *
 * Mostra também as **pendências de sincronização**: se há ação esperando rede, o
 * entregador precisa saber antes de encerrar o dia — senão vai embora achando
 * que registrou tudo.
 */
export default function Ganhos() {
    const [atualizando, setAtualizando] = useState(false)
    const { pendentes, sincronizando, sincronizar } = useSincronizacao()

    const extrato = useQuery({
        queryKey: ["entregador", "extrato"],
        queryFn: () => VendaService.MeuExtrato(),
    })

    const estoque = useQuery({
        queryKey: ["entregador", "estoque"],
        queryFn: () => VendaService.MeuEstoque(),
    })

    const atualizar = async () => {
        setAtualizando(true)
        await Promise.all([extrato.refetch(), estoque.refetch(), sincronizar()])
        setAtualizando(false)
    }

    const e = extrato.data?.data
    const est = estoque.data?.data

    return (
        <ScrollView
            style={s.tela}
            contentContainerStyle={{ padding: 16, gap: 12 }}
            refreshControl={<RefreshControl refreshing={atualizando} onRefresh={atualizar} />}
        >
            {pendentes > 0 && (
                <Cartao style={{ borderLeftWidth: 4, borderLeftColor: COLORS.danger }}>
                    <Text style={s.titulo}>Aguardando envio</Text>
                    <Text style={s.pendencia}>
                        {pendentes} {pendentes === 1 ? "ação registrada" : "ações registradas"} sem sinal.
                    </Text>
                    <Text style={s.aviso}>
                        {sincronizando ? "Enviando..." : "Vão automaticamente quando a rede voltar."}
                    </Text>
                </Cartao>
            )}

            <Cartao>
                <Text style={s.titulo}>Meus ganhos</Text>
                {e ? (
                    <>
                        <Text style={s.periodo}>
                            {data(e.periodo.inicio)} a {data(e.periodo.fim)}
                        </Text>
                        <Text style={s.total}>{brl(e.total.total)}</Text>

                        {/* Percentual e repasse aparecem separados porque o modelo
                            misto soma os dois — e o franqueado quer conferir cada
                            parcela, não só o total. */}
                        <View style={s.parcelas}>
                            {e.total.percentual > 0 && (
                                <View style={s.parcela}>
                                    <Text style={s.parcelaRotulo}>Comissão</Text>
                                    <Text style={s.parcelaValor}>{brl(e.total.percentual)}</Text>
                                </View>
                            )}
                            {e.total.repasse > 0 && (
                                <View style={s.parcela}>
                                    <Text style={s.parcelaRotulo}>Repasse</Text>
                                    <Text style={s.parcelaValor}>{brl(e.total.repasse)}</Text>
                                </View>
                            )}
                        </View>

                        {e.pedidos.length === 0 ? (
                            <Text style={s.aviso}>Nenhuma venda concluída no período.</Text>
                        ) : (
                            e.pedidos.map((p) => (
                                <View key={p.pedido_id} style={s.linha}>
                                    <View>
                                        <Text style={s.pedido}>Pedido #{p.pedido_id}</Text>
                                        <Text style={s.aviso}>{data(p.datahora)}</Text>
                                    </View>
                                    <Text style={s.comissao}>{brl(p.comissao)}</Text>
                                </View>
                            ))
                        )}
                    </>
                ) : (
                    <Text style={s.aviso}>Carregando...</Text>
                )}
            </Cartao>

            {est && est.modo_estoque && (
                <Cartao>
                    <View style={s.cabecalho}>
                        <Text style={s.titulo}>Comigo agora</Text>
                        <Etiqueta texto={est.modo_estoque === "consignacao" ? "Consignação" : "Compra"} />
                    </View>

                    {est.itens.length === 0 ? (
                        <Text style={s.aviso}>Sem mercadoria em seu poder.</Text>
                    ) : (
                        est.itens.map((i) => (
                            <View key={i.produto_id} style={s.linha}>
                                <Text style={s.produto}>{i.produto}</Text>
                                <Text style={s.quantidade}>{i.quantidade}</Text>
                            </View>
                        ))
                    )}
                </Cartao>
            )}
        </ScrollView>
    )
}

const s = StyleSheet.create({
    tela: { flex: 1, backgroundColor: COLORS.bg },
    cabecalho: { flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginBottom: 8 },
    titulo: { fontSize: 13, fontWeight: "700", color: COLORS.muted, marginBottom: 8 },
    periodo: { fontSize: 12, color: COLORS.muted },
    total: { fontSize: 30, fontWeight: "800", color: COLORS.text, marginVertical: 4 },
    parcelas: { flexDirection: "row", gap: 16, marginBottom: 10 },
    parcela: { flex: 1 },
    parcelaRotulo: { fontSize: 12, color: COLORS.muted },
    parcelaValor: { fontSize: 16, fontWeight: "700", color: COLORS.text },
    linha: {
        flexDirection: "row", justifyContent: "space-between", alignItems: "center",
        paddingVertical: 8, borderTopWidth: 1, borderTopColor: COLORS.border,
    },
    pedido: { fontSize: 15, color: COLORS.text },
    comissao: { fontSize: 15, fontWeight: "700", color: COLORS.success },
    produto: { fontSize: 15, color: COLORS.text },
    quantidade: { fontSize: 16, fontWeight: "700", color: COLORS.text },
    pendencia: { fontSize: 15, fontWeight: "600", color: COLORS.danger },
    aviso: { fontSize: 12, color: COLORS.muted, marginTop: 4 },
})
