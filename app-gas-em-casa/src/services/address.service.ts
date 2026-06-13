import { APP } from "@/constants/app"
import Http from "@/helpers/http"
import { Geocode } from "@/types/google-geocode"
import { Address } from "@/types/types"

type MkFavorite = {
    address_id: number | undefined
    client_id: number | undefined
}

const GetGeocode = (lat: number, lng: number): Promise<Geocode> => {
    let key = APP.gap_key
    let latlng = `${lat},${lng}`
    const url = `https://maps.google.com/maps/api/geocode/json?key=${key}&latlng=${latlng}`
    return Http.SendRequest(url, "GET", null, false)
}

const GetById = (address_id: number): Promise<Address> => {
    return Http.PrepareRequest(`address/get?address_id=${address_id}`, "GET")
}

const Store = ({ data }: { data: any }) => {
    return Http.PrepareRequest("address/create", "POST", data)
}

const Update = ({ data }: { data: any }) => {
    return Http.PrepareRequest("address/update", "PUT", data)
}

const MakeFavorite = ({ address_id, client_id }: MkFavorite) => {
    return Http.PrepareRequest(
        `address/makeFavorite?id=${address_id}&cliente_id=${client_id}`,
        "PUT",
    )
}

const Delete = ({ address_id }: { address_id: number | undefined }) => {
    return Http.PrepareRequest(`address/delete?id=${address_id}`, "DELETE")
}

const AddressService = {
    GetGeocode,
    GetById,
    Store,
    Update,
    MakeFavorite,
    Delete,
}

export default AddressService
