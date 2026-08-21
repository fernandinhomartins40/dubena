import { Cartao, Etiqueta } from "@/components/ui"
import { COLORS } from "@/constants/app"
import VendaService, { type Solicitacao } from "@/services/venda.service"
import { useQuery } from "@tanstack/react-query"
import { useState } from "react"
import { RefreshControl, ScrollView, StyleSheet, Text, View } from "react-native"

const brl = (v: number) => `R$ ${Number(v).toFixed(2).replace(".", ",")}`
const quando = (iso: string) =>
    new Date(iso).toLocaleString("pt-BR", { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" })

/**
 * Minhas solicitações (F4) — o que pedi à Central e no que deu.
 *
 * Sem esta tela o vendedor pede o desconto e fica no escuro: não sabe se foi
 * aprovado, recusado, nem quanto saiu. Isso o empurra a ligar para a Central
 * para perguntar — que é exatamente o telefonema que o fluxo deveria eliminar.
 */
export default function Solicitacoes() {
    const [atualizando, setAtualizando] = useState(false)

    const lista = useQuery({
        queryKey: ["entregador", "solicitacoes"],
        queryFn: VendaService.Solicitacoes,
    })

    const atualizar = async () => {
        setAtualizando(true)
        await lista.refetch()
        setAtualizando(false)
    }

    const itens = lista.data?.data ?? []

    return (
        <ScrollView
            style={s.tela}
            contentContainerStyle={{ padding: 16, gap: 10 }}
            refreshControl={<RefreshControl refreshing={atualizando} onRefresh={atualizar} />}
        >
            {itens.length === 0 && (
                <Cartao>
                    <Text style={s.vazio}>Você ainda não solicitou nenhuma venda.</Text>
                </Cartao>
            )}

            {itens.map((sol) => (
                <CartaoSolicitacao key={sol.id} solicitacao={sol} />
            ))}
        </ScrollView>
    )
}

function CartaoSolicitacao({ solicitacao: sol }: { solicitacao: Solicitacao }) {
    const bruto = (sol.itens ?? []).reduce(
        (acc, i) => acc + Number(i.quantidade) * Number(i.preco_unitario),
        0,
    )

    return (
        <Cartao>
            <View style={s.cabecalho}>
                <Text style={s.cliente}>{sol.cliente?.nome ?? "Cliente"}</Text>
                <Etiqueta texto={rotulo(sol.situacao)} />
            </View>

            <Text style={s.quando}>{quando(sol.created_at)}</Text>

            <View style={s.linha}>
                <Text style={s.rotulo}>Valor</Text>
                <Text style={s.valor}>{brl(bruto)}</Text>
            </View>

            {Number(sol.desconto_solicitado) > 0 && (
                <View style={s.linha}>
                    <Text style={s.rotulo}>Desconto pedido</Text>
                    <Text style={s.valor}>{brl(sol.desconto_solicitado)}</Text>
                </View>
            )}

            {/* A contraproposta é o caso comum: pediu 10%, a Central liberou 5%.
                Mostrar só "aprovada" esconderia a diferença que o vendedor
                precisa saber ANTES de falar com o cliente. */}
            {sol.situacao === "aprovada" && (
                <View style={s.linha}>
                    <Text style={s.rotulo}>Desconto aprovado</Text>
                    <Text style={[s.valor, { color: COLORS.success }]}>
                        {brl(sol.desconto_aprovado ?? 0)}
                    </Text>
                </View>
            )}

            {sol.motivo_decisao && <Text style={s.motivo}>"{sol.motivo_decisao}"</Text>}

            {sol.pedido && <Text style={s.pedido}>Pedido #{sol.pedido.id} gerado</Text>}
        </Cartao>
    )
}

const rotulo = (situacao: Solicitacao["situacao"]) =>
    ({
        pendente: "Aguardando",
        aprovada: "Aprovada",
        recusada: "Recusada",
        cancelada: "Cancelada",
    })[situacao]

const s = StyleSheet.create({
    tela: { flex: 1, backgroundColor: COLORS.bg },
    cabecalho: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
    cliente: { fontSize: 16, fontWeight: "600", color: COLORS.text, flex: 1 },
    quando: { fontSize: 12, color: COLORS.muted, marginBottom: 6 },
    linha: { flexDirection: "row", justifyContent: "space-between", paddingVertical: 3 },
    rotulo: { fontSize: 14, color: COLORS.muted },
    valor: { fontSize: 14, fontWeight: "600", color: COLORS.text },
    motivo: { fontSize: 13, fontStyle: "italic", color: COLORS.muted, marginTop: 6 },
    pedido: { fontSize: 13, fontWeight: "600", color: COLORS.success, marginTop: 6 },
    vazio: { fontSize: 14, color: COLORS.muted, textAlign: "center" },
})
