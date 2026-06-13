import { AddressType } from "@/types/types"
import Ionicons from "@expo/vector-icons/Ionicons"
import MaterialCommunityIcons from "@expo/vector-icons/MaterialCommunityIcons"
import Foundation from "@expo/vector-icons/Foundation"

interface AddressTypeIconProps {
    type: string
    size: number
    color: string
}

const AddressTypeIcon = ({ type, size, color }: AddressTypeIconProps) => {
    switch (type) {
        case AddressType.Home:
            return <Ionicons name="home" size={size} color={color} />
        case AddressType.Workplace:
            return <MaterialCommunityIcons name="office-building" size={size} color={color} />
        default:
            return <Foundation name="marker" size={size} color={color} />
    }
}

export default AddressTypeIcon
