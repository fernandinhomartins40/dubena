import { MMKV } from "react-native-mmkv"
import { StateStorage } from "zustand/middleware"

const storage = new MMKV({
    id: "default-storage",
})

export const zustandStorage: StateStorage = {
    setItem: (key: string, value: string) => {
        return storage.set(key, value)
    },
    getItem: function (key: string) {
        const value = storage.getString(key)
        return value ?? null
    },
    removeItem: function (key: string) {
        return storage.delete(key)
    },
}

export default storage
