import { BottomSheetModal, BottomSheetModalProps } from "@gorhom/bottom-sheet"
import React, { useCallback, useRef } from "react"
import { BackHandler, NativeEventSubscription } from "react-native"

/**
 * hook that dismisses the bottom sheet on the hardware back button press if it is visible
 * @param bottomSheetRef ref to the bottom sheet which is going to be closed/dismissed on the back press
 */
const useBottomSheetBackHandler = (bottomSheetRef: React.RefObject<BottomSheetModal | null>) => {
    const backhandlerSubscriptionRef = useRef<NativeEventSubscription | null>(null)

    const handleSheetPositionChange = useCallback<NonNullable<BottomSheetModalProps["onChange"]>>(
        (index) => {
            const isVisible = index >= 0

            if (isVisible && !backhandlerSubscriptionRef.current) {
                backhandlerSubscriptionRef.current = BackHandler.addEventListener(
                    "hardwareBackPress",
                    () => {
                        bottomSheetRef.current?.dismiss()
                        return true
                    },
                )
            } else if (!isVisible) {
                backhandlerSubscriptionRef.current?.remove()
                backhandlerSubscriptionRef.current = null
            }
        },
        [backhandlerSubscriptionRef, bottomSheetRef],
    )

    return { handleSheetPositionChange }
}

export default useBottomSheetBackHandler
