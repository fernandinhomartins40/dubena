import { INTERNAL_BUILD_NUMBER } from "@/constants/app"
import { UserFormSchema } from "@/types/types"
import { BottomSheetModal, BottomSheetScrollView, useBottomSheetModal } from "@gorhom/bottom-sheet"
import React, { forwardRef, useMemo } from "react"
import { Alert, StyleSheet, Text, View } from "react-native"
import UserForm from "../templates/UserForm"
import { screenPadding } from "@/styles/theme"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import UserService from "@/services/user.service"
import useBottomSheetBackHandler from "@/hooks/useBottomSheetBackHandler"
import useFlashStore from "@/store/flashStore"

type Ref = BottomSheetModal

/**
 * Edição de dados pessoais (F3b). Carrega o perfil do cliente (GET app/v1/perfil) e
 * atualiza via PUT app/v1/perfil. Gás do Povo permitido vem da config do app.
 */
const UserSheet = forwardRef<Ref, {}>((_props, ref) => {
    const snapPoints = useMemo(() => ["50%", "90%"], [])
    const { handleSheetPositionChange } = useBottomSheetBackHandler(
        ref as React.RefObject<BottomSheetModal>,
    )
    const { appConfig } = useFlashStore()
    const { dismiss } = useBottomSheetModal()
    const queryClient = useQueryClient()
    const { data: perfil } = useQuery({
        queryKey: ["perfil"],
        queryFn: () => UserService.GetPerfil(),
    })
    const { mutate, isPending } = useMutation({
        mutationFn: UserService.UpdatePerfil,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["perfil"] })
            dismiss()
        },
        onError: (err: any) => {
            Alert.alert(err?.message ?? "Erro ao atualizar seu cadastro.")
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

    const onSave = async (payload: any) => {
        mutate({
            nome: payload.nome,
            cpf: payload.cpf,
            datanascimento: payload.datanascimento,
        })
    }

    return (
        <BottomSheetModal
            ref={ref}
            snapPoints={snapPoints}
            index={2}
            onChange={handleSheetPositionChange}
        >
            <BottomSheetScrollView>
                <View
                    style={{ flexDirection: "column", paddingHorizontal: screenPadding.horizontal }}
                >
                    <View>
                        <Text style={styles.title}>Dados Pessoais</Text>
                    </View>

                    <UserForm
                        isSubmitting={isPending}
                        onSave={onSave}
                        user={form}
                        isGpAllowed={!!appConfig?.gaspovo_ativo}
                    />
                </View>
            </BottomSheetScrollView>
        </BottomSheetModal>
    )
})

const styles = StyleSheet.create({
    title: {
        textAlign: "center",
        fontSize: 20,
        fontWeight: "bold",
    },
})

export default UserSheet
