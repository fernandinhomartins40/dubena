import { APP } from "@/constants/app"
import Http from "@/helpers/http"
import { Geocode } from "@/types/google-geocode"
import { ClienteEnderecoApi } from "@/types/types"

/**
 * AddressService (F3b → ERP-NOVO). Múltiplos endereços de entrega do cliente do token
 * (app/v1/enderecos) + geocode via Google (host externo).
 */

const GetGeocode = (lat: number, lng: number): Promise<Geocode> => {
    const key = APP.gap_key
    const url = `https://maps.google.com/maps/api/geocode/json?key=${key}&latlng=${lat},${lng}`
    return Http.SendRequest(url, "GET")
}

const GetAll = (): Promise<ClienteEnderecoApi[]> => Http.PrepareRequest("app/v1/enderecos", "GET")

const Store = ({ data }: { data: any }): Promise<ClienteEnderecoApi> =>
    Http.PrepareRequest("app/v1/enderecos", "POST", data)

const Update = ({ id, data }: { id: number; data: any }): Promise<ClienteEnderecoApi> =>
    Http.PrepareRequest(`app/v1/enderecos/${id}`, "PUT", data)

const MakeFavorite = ({ id }: { id: number }): Promise<ClienteEnderecoApi> =>
    Http.PrepareRequest(`app/v1/enderecos/${id}/favorito`, "PUT")

const Delete = ({ id }: { id: number }) => Http.PrepareRequest(`app/v1/enderecos/${id}`, "DELETE")

const AddressService = {
    GetGeocode,
    GetAll,
    Store,
    Update,
    MakeFavorite,
    Delete,
}

export default AddressService
