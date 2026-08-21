import { Botao, Campo, Cartao } from "@/components/ui"
import { COLORS } from "@/constants/app"
import VendaService from "@/services/venda.service"
import { fontSize } from "@/styles/theme"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { router } from "expo-router"
import { useState } from "react"
import { ScrollView, StyleSheet, Text } from "react-native"
import Toast from "react-native-toast-message"

/**
 * Cadastrar cliente em campo — equivalente da tela `Cliente` do NFWEB.
 *
 * Depois de salvar, **vai direto para a solicitação** com o cliente já
 * escolhido: quem cadastra na porta é porque vai vender agora, e obrigar a
 * buscar de novo o que acabou de criar seria trabalho à toa.
 *
 * Passa pela fila offline — cadastrar sem sinal é o caso comum em rota.
 */
export default function ClienteNovo() {
    const qc = useQueryClient()

    const [nome, setNome] = useState("")
    const [documento, setDocumento] = useState("")
    const [telefone, setTelefone] = useState("")
    const [endereco, setEndereco] = useState("")
    const [numero, setNumero] = useState("")
    const [referencia, setReferencia] = useState("")

    const salvar = useMutation({
        mutationFn: () => {
            const doc = documento.replace(/\D/g, "")

            return VendaService.CadastrarCliente({
                nome: nome.trim(),
                // 11 dígitos = CPF, 14 = CNPJ. O servidor normaliza de novo, mas
                // mandar no campo certo evita gravar CNPJ como CPF.
                ...(doc.length === 14 ? { cnpj: doc } : doc ? { cpf: doc } : {}),
                telefone: telefone || undefined,
                endereco: endereco || undefined,
                numero: numero || undefined,
                ponto_referencia: referencia || undefined,
            })
        },
        onSuccess: (r) => {
            qc.invalidateQueries({ queryKey: ["entregador", "clientes"] })

            if (r.enfileirado) {
                // Sem id local não dá para seguir para a venda: o cliente ainda
                // não existe no servidor. Ser explícito evita o vendedor achar
                // que pode continuar.
                Toast.show({
                    type: "success",
                    text1: "Guardado",
                    text2: "Sem sinal. O cadastro vai quando a rede voltar.",
                })
                router.back()
                return
            }

            const criado = r.data?.data
            Toast.show({ type: "success", text1: "Cliente cadastrado" })
            router.replace({
                pathname: "/(app)/solicitar-venda",
                params: { cliente_id: String(criado?.id), nome: criado?.nome ?? nome },
            })
        },
        onError: (e: any) => {
            Toast.show({
                type: "error",
                text1: "Não foi possível cadastrar",
                // 422 do DomainException: telefone duplicado, por exemplo.
                text2: e?.message ?? "Confira os dados e tente novamente.",
            })
        },
    })

    return (
        <ScrollView style={s.tela} contentContainerStyle={{ padding: 16, gap: 12 }} keyboardShouldPersistTaps="handled">
            <Cartao>
                <Text style={s.secao}>Identificação</Text>
                <Campo label="Nome *" value={nome} onChangeText={setNome} placeholder="Nome do cliente" />
                <Campo
                    label="CPF ou CNPJ"
                    value={documento}
                    onChangeText={setDocumento}
                    keyboardType="number-pad"
                    placeholder="Somente números"
                />
                <Campo
                    label="Telefone"
                    value={telefone}
                    onChangeText={setTelefone}
                    keyboardType="phone-pad"
                    placeholder="(42) 99999-9999"
                />
            </Cartao>

            <Cartao>
                <Text style={s.secao}>Endereço</Text>
                <Campo label="Rua" value={endereco} onChangeText={setEndereco} placeholder="Rua / Avenida" />
                <Campo label="Número" value={numero} onChangeText={setNumero} keyboardType="number-pad" />
                <Campo
                    label="Ponto de referência"
                    value={referencia}
                    onChangeText={setReferencia}
                    placeholder="Ex.: portão azul, ao lado do mercado"
                />
            </Cartao>

            <Botao
                titulo="Cadastrar e vender"
                onPress={() => salvar.mutate()}
                carregando={salvar.isPending}
                desabilitado={nome.trim() === "" || salvar.isPending}
            />
        </ScrollView>
    )
}

const s = StyleSheet.create({
    tela: { flex: 1, backgroundColor: COLORS.bg },
    secao: { fontSize: fontSize.sm, fontWeight: "700", color: COLORS.muted, marginBottom: 10 },
})
