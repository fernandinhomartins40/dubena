import { View, Text, StyleSheet, Pressable } from "react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import { ChevronLeft } from "lucide-react-native"
import { useRouter } from "expo-router"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import Toast from "react-native-toast-message"
import UserForm from "@/components/templates/UserForm"
import UserService from "@/services/user.service"
import useFlashStore from "@/store/flashStore"
import { INTERNAL_BUILD_NUMBER } from "@/constants/app"
import { UserFormSchema } from "@/types/types"
import { colors, fontSize, fontStyle } from "@/styles/theme"

/**
 * Tela dedicada de Dados Pessoais (antes um bottom sheet — ruim p/ formulário com
 * teclado). Header próprio + scroll; reusa o UserForm. Atualiza via PUT app/v1/perfil.
 */
export default function PerfilDados() {
    const { top } = useSafeAreaInsets()
    const router = useRouter()
    const { appConfig } = useFlashStore()
    const queryClient = useQueryClient()

    const { data: perfil } = useQuery({
        queryKey: ["perfil"],
        queryFn: () => UserService.GetPerfil(),
    })

    const { mutate, isPending } = useMutation({
        mutationFn: UserService.UpdatePerfil,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["perfil"] })
            Toast.show({ type: "success", text1: "Dados atualizados." })
            router.back()
        },
        onError: (err: any) => {
            Toast.show({ type: "error", text1: err?.message ?? "Erro ao atualizar seu cadastro." })
        },
    })

    const form: UserFormSchema = {
        id: perfil?.id,
        nome: perfil?.nome || "",
        telefone: perfil?.telefones?.[0] || "",
        conveniado: false,
        gasdopovo: !!perfil?.gasdopovo,
        cpf: perfil?.cpf || "",
        datanascimento: perfil?.datanascimento ?? "",
        internal_build_number: INTERNAL_BUILD_NUMBER,
        pushregistration_id: "",
        sexo: "",
    }

    const onSave = (payload: any) => {
        mutate({ nome: payload.nome, cpf: payload.cpf, datanascimento: payload.datanascimento })
    }

    return (
        <View style={[styles.screen, { paddingTop: top }]}>
            <View style={styles.header}>
                <Pressable onPress={() => router.back()} hitSlop={12} style={styles.backBtn}>
                    <ChevronLeft size={24} color={colors.text} />
                </Pressable>
                <Text style={styles.headerTitle}>Dados pessoais</Text>
                <View style={{ width: 40 }} />
            </View>

            <View style={styles.body}>
                <UserForm
                    isSubmitting={isPending}
                    onSave={onSave}
                    user={form}
                    isGpAllowed={!!appConfig?.gaspovo_ativo}
                />
            </View>
        </View>
    )
}

const styles = StyleSheet.create({
    screen: { flex: 1, backgroundColor: colors.background },
    header: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        paddingHorizontal: 12,
        paddingVertical: 8,
    },
    backBtn: { width: 40, height: 40, borderRadius: 999, alignItems: "center", justifyContent: "center" },
    headerTitle: { fontSize: fontSize.md, color: colors.text, ...fontStyle.bold },
    body: { flex: 1, paddingHorizontal: 16, paddingTop: 8 },
})
