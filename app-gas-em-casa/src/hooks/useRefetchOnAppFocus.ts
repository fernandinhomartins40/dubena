import { useQueryClient } from "@tanstack/react-query"
import { useEffect, useRef } from "react"
import { AppState } from "react-native"

const useRefetchOnAppFocus = (key: string | null = null) => {
    const queryClient = useQueryClient()
    const appStateRef = useRef(AppState.currentState)

    useEffect(() => {
        const subscription = AppState.addEventListener("change", (nextState) => {
            const { current: curState } = appStateRef

            if (curState.match(/inactive|background/) && nextState === "active") {
                if (key == null) queryClient.invalidateQueries()
                else queryClient.invalidateQueries({ queryKey: [key] })
            }

            appStateRef.current = nextState
        })

        return () => {
            subscription.remove()
        }
    }, [queryClient, key])
}

export default useRefetchOnAppFocus
