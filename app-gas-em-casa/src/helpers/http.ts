import { APP } from "@/constants/app"
import useAppStore from "@/store/appStore"
import axios from "axios"

type HttpVerbs = "GET" | "POST" | "PATCH" | "PUT" | "DELETE"

const api = (method: HttpVerbs = "GET", headers: any = {}) => {
    return axios.create({
        method: method.toUpperCase(),
        headers: { ...headers, "Content-Type": "application/json", Accept: "application/json" },
    })
}

const processError = (error: any) => {
    let objError = { status: error?.status || 400, message: error?.message, errors: [] }

    if (error?.response) {
        objError = { status: error.response.status, message: error.message, errors: [] }
    }

    if (error?.response?.data) {
        objError = { status: error.response.status, message: error.response.data.msg, errors: [] }
    }

    if (error?.response?.data?.errors) {
        objError = {
            status: error.response.status,
            message: error.msg,
            errors: error.response.data.errors,
        }
    }

    return objError
}

const PrepareRequest = async (
    endpoint: string,
    method: HttpVerbs = "GET",
    data: any = null,
    isProtected: boolean = true,
    baseUrl: string | undefined = APP.api_url,
) => {
    const url = baseUrl + endpoint

    let response = await SendRequest(url, method, data, isProtected)

    return "data" in response ? response.data : response
}

const SendRequest = async (url: string, method: HttpVerbs, data: any, isProtected: boolean) => {
    try {
        const apiToken = useAppStore.getState().apiToken
        let headers = {}

        if (isProtected) headers = { ...headers, authorization: `Bearer ${apiToken}` }

        let fetcher = api(method, headers)

        let options = { url } as any

        if (data != null) options = { ...options, data }

        let response = await fetcher.request(options)

        if (response.status >= 200 && response.status <= 204) return response.data

        throw { response }
    } catch (error) {
        let objError = processError(error)

        throw objError
    }
}

const Http = { PrepareRequest, SendRequest }

export default Http
