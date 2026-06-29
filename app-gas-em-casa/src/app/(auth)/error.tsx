import React from "react"
import { useGlobalSearchParams } from "expo-router"
import ErrorView from "@/components/templates/errorview"

const Error = () => {
    const { error } = useGlobalSearchParams()

    return <ErrorView message={error ? error : "Error"} />
}

export default Error
