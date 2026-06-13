import { INTERNAL_BUILD_NUMBER } from "@/constants/app"
import { User, UserFormSchema } from "@/types/types"
import { BottomSheetModal, BottomSheetScrollView, useBottomSheetModal } from "@gorhom/bottom-sheet"
import React, { forwardRef, useMemo } from "react"
import { Alert, StyleSheet, Text, View } from "react-native"
import UserForm from "../templates/UserForm"
import { screenPadding } from "@/styles/theme"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import UserService from "@/services/user.service"
import { getMessaging, getToken, requestPermission } from "@react-native-firebase/messaging"
import useAppStore from "@/store/appStore"
import useBottomSheetBackHandler from "@/hooks/useBottomSheetBackHandler"
import { getApp } from "@react-native-firebase/app"
import useFlashStore from "@/store/flashStore"

interface Props {
    user: User
}

type Ref = BottomSheetModal

const app = getApp()

const UserSheet = forwardRef<Ref, Props>(({ user }, ref) => {
    const snapPoints = useMemo(() => ["50%", "90%"], [])
    const { handleSheetPositionChange } = useBottomSheetBackHandler(
        ref as React.RefObject<BottomSheetModal>,
    )
    const { setUser } = useAppStore()
    const { store } = useFlashStore()
    const { dismiss } = useBottomSheetModal()
    const queryClient = useQueryClient()
    const { mutate, isPending } = useMutation({
        mutationFn: UserService.Update,
        onSuccess: (data) => {
            setUser(data)

            queryClient.invalidateQueries({ queryKey: ["root"] })

            dismiss()
        },
        onError: (err) => {
            console.error(err)

            if ("message" in err) {
                Alert.alert(String(err.message))
            } else {
                Alert.alert("Ocorreu um erro desconhecido ao tentar atualizar seu cadastro..")
            }
        },
    })

    const standardizeDate = (userDate: string): string => {
        let brkDate = userDate.split("-")
        return brkDate[2] + "/" + brkDate[1] + "/" + brkDate[0]
    }

    const form: UserFormSchema = {
        id: user.id,
        nome: user.nome || "",
        telefone: user.telefone || "",
        conveniado: !!user.conveniado || false,
        gasdopovo: !!user.gasdopovo || false,
        cpf: user.cpf || "",
        datanascimento: user?.datanascimento ? standardizeDate(user.datanascimento) : "",
        internal_build_number: INTERNAL_BUILD_NUMBER,
        pushregistration_id: "",
        sexo: user.sexo || "",
    }

    const onSave = async (payload: any) => {
        const messaging = getMessaging(app)

        await requestPermission(messaging)

        const fcmtoken = await getToken(messaging)

        payload.pushregistration_id = fcmtoken

        mutate({ data: payload })
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
                        isGpAllowed={!!store?.gaspovoativado}
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
