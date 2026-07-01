import { colors, defaultStyles, fontSize, fontStyle } from "@/styles/theme"
import { useGlobalSearchParams, useRouter } from "expo-router"
import { useEffect, useMemo, useRef, useState } from "react"
import {
    ActivityIndicator,
    Alert,
    Platform,
    Pressable,
    StyleSheet,
    Text,
    View,
} from "react-native"
import MapView, { Marker, PROVIDER_DEFAULT, PROVIDER_GOOGLE, Region } from "react-native-maps"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import * as Location from "expo-location"
import { ChevronLeft } from "lucide-react-native"
import { APP, DEFAULT_LOCATION } from "@/constants/app"
import Button from "@/components/atoms/button"
import {
    GooglePlaceData,
    GooglePlaceDetail,
    GooglePlacesAutocomplete,
    GooglePlacesAutocompleteProps,
} from "react-native-google-places-autocomplete"
import { createPlacesAutocompleteSessionToken, delay, formatFromGMaps } from "@/helpers/utils"
import { Address as AddressType, GMapsAddress } from "@/types/types"
import { useQuery } from "@tanstack/react-query"
import AddressService from "@/services/address.service"
import { LocateFixed, ArrowRight } from "lucide-react-native"
import IconButton from "@/components/atoms/iconbutton"
import AddressFormModal from "@/components/organism/AddressFormModal"
import useDebounce from "@/hooks/useDebounce"
import LoaderOverlay from "@/components/atoms/LoaderOverlay"

// ? Currently the library is incompatible with EXPO 53 and has a lot of bugs
// ? The only way to solve it is to just pick up all the default props and use them
const defaultProps: GooglePlacesAutocompleteProps = {
    autoFillOnNotFound: false,
    currentLocation: false,
    currentLocationLabel: "Current location",
    debounce: 0,
    disableScroll: false,
    enableHighAccuracyLocation: true,
    enablePoweredByContainer: true,
    fetchDetails: false,
    filterReverseGeocodingByTypes: [],
    GooglePlacesDetailsQuery: {},
    GooglePlacesSearchQuery: {
        rankby: "distance",
        type: "restaurant",
    },
    GoogleReverseGeocodingQuery: {},
    isRowScrollable: true,
    keepResultsAfterBlur: false,
    keyboardShouldPersistTaps: "always",
    listHoverColor: "#ececec",
    listUnderlayColor: "#c8c7cc",
    listViewDisplayed: "auto",
    minLength: 0,
    nearbyPlacesAPI: "GooglePlacesSearch",
    numberOfLines: 1,
    onFail: () => {},
    onNotFound: () => {},
    onPress: () => {},
    onTimeout: () => console.warn("google places autocomplete: request timeout"),
    placeholder: "",
    predefinedPlaces: [],
    predefinedPlacesAlwaysVisible: false,
    query: {
        key: "missing api key",
        language: "en",
        type: "geocode",
    },
    styles: {},
    suppressDefaultStyles: false,
    textInputHide: false,
    textInputProps: {},
    timeout: 20000,
}

const DELAY_IN_MS = 12 * 1000

const MAX_TRIES = 3

const Address = () => {
    const { address_id } = useGlobalSearchParams()
    const { top } = useSafeAreaInsets()
    const router = useRouter()
    const mapRef = useRef<any>(null)
    const [isLocLoading, setIsLocLoading] = useState(false)
    const [isManual, setIsManual] = useState(true)
    const [completeAddress, setCompleteAddress] = useState("")
    const [addressObj, setAddressObj] = useState<GMapsAddress | null | AddressType>(null)
    const [open, setOpen] = useState(false)
    const latitudeDelta = 0.005
    const longitudeDelta = 0.005
    const defaultLocation = useMemo(
        () => ({
            latitude: DEFAULT_LOCATION.latitude,
            longitude: DEFAULT_LOCATION.longitude,
            latitudeDelta,
            longitudeDelta,
        }),
        [],
    )
    const [location, setLocation] = useState<Region>(defaultLocation)
    const debLocation = useDebounce(location, 500)
    const region = useDebounce(location, 5)
    const {
        data: geocode,
        isLoading,
        refetch,
    } = useQuery({
        queryKey: ["geocode", debLocation.latitude, debLocation.longitude],
        queryFn: () => AddressService.GetGeocode(debLocation.latitude, debLocation.longitude),
        enabled: false,
    })
    const {
        data: addressList,
        refetch: refetchAddress,
        isLoading: isFetchingAddress,
    } = useQuery({
        queryKey: ["addresses"],
        queryFn: () => AddressService.GetAll(),
        enabled: !!address_id,
    })
    // Edição: encontra o endereço pelo id na lista (não há GET por id no app/v1).
    const address = addressList?.find((a) => a.id === Number(address_id)) ?? null

    useEffect(() => {
        if (address) {
            const lat = address.latitude ?? defaultLocation.latitude
            const lng = address.longitude ?? defaultLocation.longitude
            const toLoc: Region = {
                latitudeDelta,
                longitudeDelta,
                latitude: lat,
                longitude: lng,
            }

            setLocation(toLoc)

            // Mapeia o endereço (ClienteEnderecoApi) para o formato do form (GMapsAddress).
            const mapped: GMapsAddress = {
                uf: address.uf ?? "",
                siglaPais: "BR",
                pais: "Brasil",
                cep: address.cep ?? "",
                cidade: address.cidade ?? "",
                bairro: address.bairro ?? "",
                numero: address.numero ?? "",
                rua: address.endereco ?? "",
                latitude: lat,
                longitude: lng,
            }
            setAddressObj((prev) => ({ ...(prev as GMapsAddress), ...mapped }))

            mapRef.current?.animateToRegion(toLoc, 2000)
        }
    }, [address])

    useEffect(() => {
        if (address_id) {
            refetchAddress()
        }
    }, [address_id])

    useEffect(() => {
        if (isManual || completeAddress == "") refetch()
    }, [debLocation])

    useEffect(() => {
        if (geocode) {
            let first = geocode.results[0]

            if (first) {
                let formatted = formatFromGMaps(first.address_components, first.geometry as any)

                if (!formatted) return

                setAddressObj((prev) => ({ ...prev, ...formatted }))

                setCompleteAddress(
                    (formatted.rua ? formatted.rua + ", " : "") +
                        (formatted.numero ? formatted.numero + ", " : "") +
                        (formatted.bairro ? formatted.bairro : ""),
                )
            }
        }
    }, [geocode])

    const goToUser = async () => {
        setIsLocLoading(true)
        let userLoc = await getUserLocation()

        if (userLoc) {
            mapRef.current?.animateToRegion(userLoc, 2000)

            setLocation(userLoc)
        }

        setIsLocLoading(false)
    }

    const getUserLocation = async () => {
        let msg = Platform.select({
            ios:
                "Para ter uma melhor experiência ao utilizar o mapa, permita o aplicativo visualizar sua localização." +
                ' Para habilitar a localização em seu iPhone, basta ir em "Ajustes -> Apps -> Gás em Casa -> Localização -> Durante Uso do App".',
            default: "...para ter uma melhor experiência ao utilizar o mapa, ative sua localização",
        })

        const enabled = await Location.hasServicesEnabledAsync()
        if (!enabled) {
            Alert.alert("Atenção...", msg)

            return null
        }

        const { status: curStatus } = await Location.getForegroundPermissionsAsync()
        if (curStatus !== "granted") {
            const { status: finalStatus } = await Location.requestForegroundPermissionsAsync()

            if (finalStatus !== "granted") {
                Alert.alert("Atenção...", msg)

                return null
            }
        }

        let tries = 1
        let loc: Location.LocationObject | null = null
        let locationError: Error | null = null
        do {
            try {
                loc = await Promise.race([delay(DELAY_IN_MS), Location.getCurrentPositionAsync()])

                if (!loc) {
                    throw new Error("Timeout")
                }
            } catch (error) {
                locationError = error as Error
            } finally {
                tries++
            }
        } while (!loc && tries <= MAX_TRIES)

        if (!loc) {
            return null
        }

        const toLoc = {
            longitudeDelta,
            latitudeDelta,
            latitude: loc.coords.latitude,
            longitude: loc.coords.longitude,
        }

        return toLoc
    }

    const onFinishedMove = (region: Region) => {
        if (isManual) {
            setLocation({
                latitude: region.latitude,
                longitude: region.longitude,
                latitudeDelta: region.latitudeDelta,
                longitudeDelta: region.longitudeDelta,
            })
        }
    }

    const onMapChange = (region: Region) => {
        if (!isManual) return

        setLocation({
            latitude: region.latitude,
            longitude: region.longitude,
            latitudeDelta: region.latitudeDelta,
            longitudeDelta: region.longitudeDelta,
        })
    }

    const handleOnPressManual = () => {
        setIsManual((prev) => !prev)
    }

    const handleOnPressFinished = () => {
        setOpen(true)
    }

    const handlePlacesPress = (_data: GooglePlaceData, detail: GooglePlaceDetail | null) => {
        let formatted = formatFromGMaps(detail?.address_components, detail?.geometry)

        if (!formatted) return

        let toLoc = {
            latitude: formatted.latitude,
            longitude: formatted.longitude,
            latitudeDelta,
            longitudeDelta,
        }
        setLocation(toLoc)

        setAddressObj((prev) => ({ ...prev, ...formatted }))

        setCompleteAddress(
            (formatted.rua ? formatted.rua + ", " : "") +
                (formatted.numero ? formatted.numero + ", " : "") +
                (formatted.bairro ? formatted.bairro : ""),
        )

        mapRef.current?.animateToRegion(toLoc, 2000)
    }

    const renderPlacesSearch = () => {
        if (isManual) {
            return (
                <View
                    style={{
                        marginTop: 10,
                        padding: 12,
                        backgroundColor: "white",
                        borderRadius: 8,
                        flexDirection: "row",
                        alignItems: "center",
                        gap: 2,
                    }}
                >
                    {isLoading ? <ActivityIndicator size={14} /> : ""}
                    <Text style={{ fontSize: 14, ...fontStyle.regular }}>{completeAddress}</Text>
                </View>
            )
        }

        return (
            <GooglePlacesAutocomplete
                {...defaultProps}
                fetchDetails
                placeholder="Qual o endereço de entrega?"
                minLength={2}
                styles={placesStyle}
                query={{
                    radius: 10000,
                    strictbounds: true,
                    location:
                        String(DEFAULT_LOCATION.latitude) +
                        ", " +
                        String(DEFAULT_LOCATION.longitude),
                    components: "country:BR",
                    key: APP.gap_key,
                    language: "pt",
                    sessiontoken: createPlacesAutocompleteSessionToken,
                }}
                GooglePlacesSearchQuery={{
                    rankby: "distance",
                    type: "cafe",
                }}
                debounce={200}
                enablePoweredByContainer={false}
                onPress={handlePlacesPress}
                predefinedPlaces={[]}
                textInputProps={{}}
            />
        )
    }

    return (
        <View style={[defaultStyles.container, { backgroundColor: colors.background, paddingTop: top }]}>
            <View style={styles.header}>
                <Pressable onPress={() => router.back()} hitSlop={12} style={styles.backBtn}>
                    <ChevronLeft size={24} color={colors.text} />
                </Pressable>
                <Text style={styles.headerTitle}>Novo endereço</Text>
                <View style={{ width: 40 }} />
            </View>

            <View style={styles.container}>
                        <View style={styles.mapContainer}>
                                <View style={{ position: "absolute", width: "100%" }}>
                                    {renderPlacesSearch()}
                                </View>

                                <MapView
                                    ref={mapRef}
                                    style={styles.map}
                                    provider={
                                        Platform.OS === "ios" ? PROVIDER_DEFAULT : PROVIDER_GOOGLE
                                    }
                                    showsUserLocation={false}
                                    showsMyLocationButton={false}
                                    rotateEnabled={false}
                                    loadingEnabled={true}
                                    pitchEnabled={false}
                                    showsIndoors={false}
                                    initialRegion={defaultLocation}
                                    onRegionChange={onMapChange}
                                    onRegionChangeComplete={onFinishedMove}
                                    onMapReady={() => {
                                        if (!address_id) goToUser()
                                    }}
                                >
                                    <Marker
                                        coordinate={region}
                                        title="Está é a minha localização"
                                        description="Aqui estou e estarei até o gás chegar em minha casa"
                                    />
                                </MapView>
                                {(isLoading || isLocLoading || isFetchingAddress) && (
                                    <View style={styles.loaderClass}>
                                        <ActivityIndicator size="large" color="#007AFF" />
                                    </View>
                                )}

                                <View style={styles.bottomRight}>
                                    <IconButton width={70} height={70} onPress={goToUser}>
                                        <LocateFixed size={34} color={colors.primary} strokeWidth={2} />
                                    </IconButton>
                                </View>
                            </View>

                            <View style={styles.confirmButtonsContainer}>
                                <View style={{ flex: 1 }}>
                                    <Button
                                        uppercase={false}
                                        title={isManual ? "Selecionar Manualmente" : "Cancelar"}
                                        onPress={handleOnPressManual}
                                        textStyle={{ fontSize: 14 }}
                                    />
                                </View>
                                <View style={{ flex: 1 }}>
                                    <Button
                                        uppercase={false}
                                        title={
                                            <View
                                                style={{
                                                    flexDirection: "row",
                                                    alignItems: "flex-start",
                                                    justifyContent: "center",
                                                }}
                                            >
                                                <Text
                                                    style={{
                                                        fontSize: 14,
                                                        color: colors.white,
                                                        ...fontStyle.semiBold,
                                                    }}
                                                >
                                                    Avançar
                                                </Text>
                                                <ArrowRight size={18} color={colors.white} strokeWidth={2.4} />
                                            </View>
                                        }
                                        onPress={handleOnPressFinished}
                                        textStyle={{ fontSize: 14 }}
                                    />
                                </View>
                            </View>
                        </View>

            <AddressFormModal address={addressObj} open={open} closeModal={() => setOpen(false)} />

            <LoaderOverlay isLoading={isFetchingAddress} />
        </View>
    )
}

const styles = StyleSheet.create({
    header: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        paddingHorizontal: 12,
        paddingVertical: 8,
    },
    backBtn: {
        width: 40,
        height: 40,
        borderRadius: 999,
        alignItems: "center",
        justifyContent: "center",
    },
    headerTitle: {
        fontSize: fontSize.md,
        color: colors.text,
        ...fontStyle.bold,
    },
    flexColumn: {
        display: "flex",
        flexDirection: "column",
    },
    container: {
        backgroundColor: colors.background,
        flex: 1,
        justifyContent: "flex-start",
        paddingHorizontal: 14,
        paddingBottom: 14,
    },
    mapContainer: {
        flex: 1,
        overflow: "hidden",
        borderRadius: 14,
        marginTop: 4,
    },
    map: {
        ...StyleSheet.absoluteFillObject,
    },
    bottomRight: {
        position: "absolute",
        bottom: 10,
        right: 10,
    },
    confirmButtonsContainer: {
        flexDirection: "row",
        justifyContent: "space-between",
        alignItems: "center",
        gap: 10,
        marginTop: 12,
    },
    loaderClass: {
        ...StyleSheet.absoluteFillObject,
        backgroundColor: "rgba(255,255,255,0.6)",
        justifyContent: "center",
        alignItems: "center",
    },
})

const placesStyle = StyleSheet.create({
    textInputContainer: {
        flex: 1,
        backgroundColor: "transparent",
        height: 30,
        marginHorizontal: 14,
        borderTopWidth: 0,
        borderBottomWidth: 0,
    },
    textInput: {
        height: 40,
        margin: 0,
        borderRadius: 8,
        padding: 12,
        fontSize: 18,
        borderWidth: StyleSheet.hairlineWidth,
        borderColor: colors.primaryMuted,
        ...fontStyle.regular,
    },
    listView: {
        borderWidth: 1,
        borderColor: "#d0d0d0",
        backgroundColor: "#FFF",
        marginHorizontal: 14,
        elevation: 5,
        shadowColor: "#000",
        shadowOpacity: 0.1,
        shadowRadius: 10,
        marginTop: 10,
        borderRadius: 5,
    },
    description: {
        fontSize: 14,
        ...fontStyle.regular,
    },
    markerSize: {
        maxWidth: 35,
        maxHeight: 35,
        minWidth: 35,
        minHeight: 35,
        width: 35,
        height: 35,
    },
    keyboardAwareGray: {
        flex: 1,
        backgroundColor: "#e3e3e3",
    },
    keyboardAwareRed: {
        flex: 1,
        backgroundColor: colors.primary,
    },
    keyboardAwareContainerFlex: {
        flex: 1,
        backgroundColor: "#e3e3e3",
        justifyContent: "space-around",
        alignItems: "center",
    },
    keyboardAwareContainer: {
        backgroundColor: "#e3e3e3",
        justifyContent: "space-around",
        alignItems: "center",
    },
})

export default Address
