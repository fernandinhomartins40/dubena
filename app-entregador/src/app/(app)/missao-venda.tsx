import { Botao, Campo, Cartao } from "@/components/ui"
import { COLORS } from "@/constants/app"
import { ArquivoUpload, tirarFoto } from "@/helpers/camera"
import MissaoService from "@/services/missao.service"
import { ProdutoVenda } from "@/types/types"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { router, useLocalSearchParams } from "expo-router"
import * as Location from "expo-location"
import { Camera, Check, Minus, Plus, UserPlus } from "lucide-react-native"
import { useState } from "react"
import { Image, ScrollView, StyleSheet, Text, TouchableOpacity, View } from "react-native"
import Toast from "react-native-toast-message"

const brl = (v: number) => `R$ ${v.toFixed(2).replace(".", ",")}`

/**
 * Vender em campo (L8) — tela dedicada. Cliente (sugerido ou cadastro rápido) +
 * produtos com quantidade + foto. O PREÇO é 100% do servidor; o total exibido é
 * apenas informativo (preço de tabela).
 */
export default function MissaoVenda() {
    const params = useLocalSearchParams<{ cliente_id?: string; nome?: string }>()
    const qc = useQueryClient()

    const [clienteId, setClienteId] = useState<number | null>(params.cliente_id ? Number(params.cliente_id) : null)
    const [clienteNome, setClienteNome] = useState(params.nome ?? "")
    const [novoNome, setNovoNome] = useState("")
    const [novoTelefone, setNovoTelefone] = useState("")
    const [novoEndereco, setNovoEndereco] = useState("")
    const [cadastrando, setCadastrando] = useState(false)
    const [qtd, setQtd] = useState<Record<number, number>>({})
    const [foto, setFoto] = useState<ArquivoUpload | null>(null)

    const { data: produtos } = useQuery({ queryKey: ["entregador", "missao-produtos"], queryFn: MissaoService.Produtos })

    const itens = Object.entries(qtd)
        .filter(([, q]) => q > 0)
        .map(([id, q]) => ({ produto_id: Number(id), quantidade: q }))
    const total = (produtos ?? []).reduce((acc, p) => acc + (qtd[p.id] ?? 0) * p.preco, 0)

    const cadastrar = async () => {
        if (!novoNome.trim()) {
            Toast.show({ type: "error", text1: "Informe o nome do cliente." })
            return
        }
        setCadastrando(true)
        try {
            let coords: { latitude: number; longitude: number } | null = null
            try {
                const pos = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced })
                coords = pos.coords
            } catch { /* sem GPS agora */ }

            const c = await MissaoService.CadastrarCliente({
                nome: novoNome.trim(),
                telefone: novoTelefone || undefined,
                endereco: novoEndereco || undefined,
                latitude: coords?.latitude ?? null,
                longitude: coords?.longitude ?? null,
            })
            setClienteId(c.id)
            setClienteNome(c.nome)
            Toast.show({ type: "success", text1: "Cliente cadastrado." })
        } catch (e: any) {
            Toast.show({ type: "error", text1: e?.message ?? "Erro ao cadastrar." })
        } finally {
            setCadastrando(false)
        }
    }

    const vender = useMutation({
        mutationFn: async () => {
            let coords: { latitude: number; longitude: number } | null = null
            try {
                const pos = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced })
                coords = pos.coords
            } catch { /* sem GPS agora */ }

            return MissaoService.VenderGas({
                cliente_id: clienteId!,
                itens,
                latitude: coords?.latitude ?? null,
                longitude: coords?.longitude ?? null,
            }, foto)
        },
        onSuccess: (r) => {
            qc.invalidateQueries({ queryKey: ["entregador", "missao"] })
            Toast.show({ type: "success", text1: `Venda registrada (pedido #${r.pedido_id}).` })
            router.back()
        },
        onError: (e: any) => Toast.show({ type: "error", text1: e?.message ?? "Erro na venda." }),
    })

    const mudarQtd = (id: number, delta: number) =>
        setQtd((q) => ({ ...q, [id]: Math.max(0, (q[id] ?? 0) + delta) }))

    return (
        <ScrollView contentContainerStyle={{ padding: 16, gap: 14 }}>
            <Text style={s.secao}>Cliente</Text>
            {clienteId ? (
                <Cartao style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
                    <View>
                        <Text style={s.clienteNome}>{clienteNome || `Cliente #${clienteId}`}</Text>
                        <Text style={s.clienteSub}>Selecionado para esta venda</Text>
                    </View>
                    <TouchableOpacity onPress={() => { setClienteId(null); setClienteNome("") }} hitSlop={10}>
                        <Text style={{ color: COLORS.danger, fontWeight: "700" }}>Trocar</Text>
                    </TouchableOpacity>
                </Cartao>
            ) : (
                <Cartao style={{ gap: 4 }}>
                    <View style={{ flexDirection: "row", alignItems: "center", gap: 8, marginBottom: 6 }}>
                        <UserPlus size={18} color={COLORS.primary} />
                        <Text style={s.clienteNome}>Cadastro rápido</Text>
                    </View>
                    <Campo label="Nome" value={novoNome} onChangeText={setNovoNome} placeholder="Nome do cliente" />
                    <Campo label="Telefone (opcional)" value={novoTelefone} onChangeText={setNovoTelefone} keyboardType="phone-pad" />
                    <Campo label="Endereço (opcional)" value={novoEndereco} onChangeText={setNovoEndereco} />
                    <Botao titulo="Cadastrar cliente" variante="secundario" onPress={cadastrar} carregando={cadastrando} />
                </Cartao>
            )}

            <Text style={s.secao}>Produtos</Text>
            <View style={{ gap: 8 }}>
                {(produtos ?? []).map((p: ProdutoVenda) => (
                    <Cartao key={p.id} style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
                        <View style={{ flex: 1 }}>
                            <Text style={s.produtoNome}>{p.descricao}</Text>
                            <Text style={s.produtoPreco}>{brl(p.preco)}</Text>
                        </View>
                        <View style={s.qtdRow}>
                            <TouchableOpacity style={s.qtdBtn} onPress={() => mudarQtd(p.id, -1)}>
                                <Minus size={16} color={COLORS.primary} />
                            </TouchableOpacity>
                            <Text style={s.qtdTexto}>{qtd[p.id] ?? 0}</Text>
                            <TouchableOpacity style={s.qtdBtn} onPress={() => mudarQtd(p.id, 1)}>
                                <Plus size={16} color={COLORS.primary} />
                            </TouchableOpacity>
                        </View>
                    </Cartao>
                ))}
            </View>

            <Text style={s.secao}>Evidência</Text>
            <TouchableOpacity style={s.fotoBox} onPress={async () => { const f = await tirarFoto(); if (f) setFoto(f) }} activeOpacity={0.8}>
                {foto ? (
                    <Image source={{ uri: foto.uri }} style={s.fotoPreview} />
                ) : (
                    <View style={{ alignItems: "center", gap: 6 }}>
                        <Camera size={28} color={COLORS.muted} />
                        <Text style={s.fotoTexto}>Foto da venda/fachada</Text>
                    </View>
                )}
            </TouchableOpacity>

            <Cartao style={{ flexDirection: "row", justifyContent: "space-between", alignItems: "center" }}>
                <Text style={s.totalLabel}>Total (tabela)</Text>
                <Text style={s.totalValor}>{brl(total)}</Text>
            </Cartao>

            <Botao
                titulo="Confirmar venda"
                onPress={() => vender.mutate()}
                carregando={vender.isPending}
                desabilitado={!clienteId || itens.length === 0}
            />
        </ScrollView>
    )
}

const s = StyleSheet.create({
    secao: { fontSize: 13, fontWeight: "700", color: COLORS.muted },
    clienteNome: { fontSize: 16, fontWeight: "700", color: COLORS.text },
    clienteSub: { fontSize: 12, color: COLORS.muted, marginTop: 2 },
    produtoNome: { fontSize: 15, fontWeight: "600", color: COLORS.text },
    produtoPreco: { fontSize: 13, color: COLORS.graphite, fontWeight: "700", marginTop: 2 },
    qtdRow: { flexDirection: "row", alignItems: "center", gap: 10 },
    qtdBtn: {
        width: 32, height: 32, borderRadius: 999, backgroundColor: "#FFF1E8",
        alignItems: "center", justifyContent: "center",
    },
    qtdTexto: { fontSize: 16, fontWeight: "800", color: COLORS.text, minWidth: 22, textAlign: "center" },
    fotoBox: {
        height: 140, borderRadius: 14, borderWidth: 1.5, borderStyle: "dashed",
        borderColor: COLORS.border, backgroundColor: COLORS.card,
        alignItems: "center", justifyContent: "center", overflow: "hidden",
    },
    fotoPreview: { width: "100%", height: "100%" },
    fotoTexto: { fontSize: 13, color: COLORS.muted },
    totalLabel: { fontSize: 14, color: COLORS.muted, fontWeight: "600" },
    totalValor: { fontSize: 18, fontWeight: "800", color: COLORS.primary },
})
