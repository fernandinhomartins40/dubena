import { AddressComponent } from "react-native-google-places-autocomplete"

export interface Geocode {
    plus_code: PlusCode
    results: Result[]
    status: string
}

export interface PlusCode {
    compound_code: string
    global_code: string
}

export interface Result {
    address_components: AddressComponent[]
    formatted_address: string
    geometry: Geometry
    place_id: string
    types: string[]
    plus_code?: PlusCode
}

export interface Geometry {
    bounds?: Bounds
    location: Location
    location_type: string
    viewport: Viewport
}

export interface Bounds {
    northeast: Northeast
    southwest: Southwest
}

export interface Northeast {
    lat: number
    lng: number
}

export interface Southwest {
    lat: number
    lng: number
}

export interface Location {
    lat: number
    lng: number
}

export interface Viewport {
    northeast: Northeast
    southwest: Southwest
}
