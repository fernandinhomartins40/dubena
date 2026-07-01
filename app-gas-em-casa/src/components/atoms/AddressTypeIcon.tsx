import { AddressType } from "@/types/types"
import { Home, Building2, MapPin } from "lucide-react-native"

interface AddressTypeIconProps {
    type: string
    size: number
    color: string
}

const AddressTypeIcon = ({ type, size, color }: AddressTypeIconProps) => {
    switch (type) {
        case AddressType.Home:
            return <Home size={size} color={color} strokeWidth={2} />
        case AddressType.Workplace:
            return <Building2 size={size} color={color} strokeWidth={2} />
        default:
            return <MapPin size={size} color={color} strokeWidth={2} />
    }
}

export default AddressTypeIcon
