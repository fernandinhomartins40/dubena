import { Banknote, CreditCard, Barcode, QrCode, WalletCards } from "lucide-react-native"
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
            return <Banknote size={size} color={color} strokeWidth={2} />
        case 1:
        case 2:
        case 6:
            return <CreditCard size={size} color={color} strokeWidth={2} />
        case 3:
            return <WalletCards size={size} color={color} strokeWidth={2} />
        case 4:
            return <Barcode size={size} color={color} strokeWidth={2} />
        case 7:
            return <QrCode size={size} color={color} strokeWidth={2} />
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
            return <CreditCard size={24} color="black" strokeWidth={2} />
    }
}

export default PaymentIcon
