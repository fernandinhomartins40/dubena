import { APP } from "@/constants/app"
import Http from "@/helpers/http"
import { Geocode } from "@/types/google-geocode"
import { Address } from "@/types/types"

type MkFavorite = {
    address_id: number | undefined
    client_id: number | undefined
}

/**
 * AddressService (F2 → ERP-NOVO).
 *
 * Geocode continua via Google (host externo). O CRUD de endereço do legado
 * (`address/*`) AINDA NÃO tem equivalente no `app/v1` do ERP-NOVO.
 *
 * TODO(F3 — Cadastros): criar no ERP-NOVO os endpoints de endereço do cliente
 * (listar/criar/editar/favoritar/excluir), escopados por token, e re-apontar aqui.
 * Até lá, estes métodos lançam erro explícito em vez de chamar o legado.
 */

const naoImplementado = (nome: string): Promise<never> => {
    return Promise.reject({
        status: 501,
        message: `Recurso de endereço "${nome}" será migrado na F3 (Cadastros) do ERP-NOVO.`,
        errors: {},
    })
}

/** Geocode reverso via Google (host externo, sem Bearer do ERP). */
const GetGeocode = (lat: number, lng: number): Promise<Geocode> => {
    const key = APP.gap_key
    const latlng = `${lat},${lng}`
    const url = `https://maps.google.com/maps/api/geocode/json?key=${key}&latlng=${latlng}`
    return Http.SendRequest(url, "GET")
}

const GetById = (_address_id: number): Promise<Address> => naoImplementado("getById")

const Store = (_args: { data: any }) => naoImplementado("create")

const Update = (_args: { data: any }) => naoImplementado("update")

const MakeFavorite = (_args: MkFavorite) => naoImplementado("makeFavorite")

const Delete = (_args: { address_id: number | undefined }) => naoImplementado("delete")

const AddressService = {
    GetGeocode,
    GetById,
    Store,
    Update,
    MakeFavorite,
    Delete,
}

export default AddressService
