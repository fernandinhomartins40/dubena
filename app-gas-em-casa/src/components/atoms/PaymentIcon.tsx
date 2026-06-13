import FontAwesome from "@expo/vector-icons/FontAwesome"
import AntDesign from "@expo/vector-icons/AntDesign"
import FontAwesome6 from "@expo/vector-icons/FontAwesome6"
import MaterialCommunityIcons from "@expo/vector-icons/MaterialCommunityIcons"
import { CardBrands } from "@/types/types"
import { Image } from "react-native"
import {
    AmexImgUri,
    EloImgUri,
    HipercardImgUri,
    MastercardImgUri,
    VisaImgUri,
} from "@/constants/images"

interface PaymentIconProps {
    type: number
    color: string
    size?: number
}

interface CardBrandSvgIconProps {
    brand: CardBrands
    width: number
    height: number
}

const PaymentIcon = ({ type, color, size = 24 }: PaymentIconProps) => {
    switch (type) {
        case 0:
            return <FontAwesome name="money" size={size} color={color} />
        case 1:
        case 2:
        case 6:
            return <AntDesign name="creditcard" size={size} color={color} />
        case 3:
            return <MaterialCommunityIcons name="card-multiple-outline" size={size} color={color} />
        case 4:
            return <MaterialCommunityIcons name="barcode" size={size} color={color} />
        case 7:
            return <FontAwesome6 name="pix" size={size} color={color} />
        default:
            return null
    }
}

export const CardBrandSvgIcon = ({ brand, width, height }: CardBrandSvgIconProps) => {
    switch (brand) {
        case "mastercard":
            return <Image source={{ uri: MastercardImgUri }} width={width} height={height} />
        case "amex":
            return <Image source={{ uri: AmexImgUri }} width={width} height={height} />
        case "elo":
            return <Image source={{ uri: EloImgUri }} width={width} height={height} />
        case "hipercard":
            return <Image source={{ uri: HipercardImgUri }} width={width} height={height} />
        case "visa":
            return <Image source={{ uri: VisaImgUri }} width={width} height={height} />
        default:
            return <AntDesign name="creditcard" size={24} color="black" />
    }
}

export default PaymentIcon
