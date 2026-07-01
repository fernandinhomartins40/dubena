import { useRef, useState } from "react"
import {
    Dimensions,
    FlatList,
    NativeScrollEvent,
    NativeSyntheticEvent,
    Platform,
    StyleSheet,
    Text,
    View,
} from "react-native"
import Animated, { FadeIn, FadeInDown } from "react-native-reanimated"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import { useRouter } from "expo-router"
import * as Location from "expo-location"
import * as Notifications from "expo-notifications"
import * as Device from "expo-device"
import { PermissionsAndroid } from "react-native"
import { Flame, MapPin, BellRing } from "lucide-react-native"
import Button from "@/components/atoms/button"
import useAppStore from "@/store/appStore"
import { colors, fontSize, fontStyle } from "@/styles/theme"

const { width } = Dimensions.get("window")

type Slide = {
    key: string
    icon: typeof Flame
    title: string
    desc: string
    cta: string
    /** Permissão pedida ao ENTRAR neste slide. */
    onEnter?: () => void
}

/**
 * Onboarding moderno (marca nova) — carrossel de 3 slides com transições suaves
 * (paging horizontal + fade/slide via reanimated) e dots animados. Substitui as
 * 3 telas de foto com texto por cima. Pede localização/notificações no slide certo.
 */
const Onboarding = () => {
    const router = useRouter()
    const insets = useSafeAreaInsets()
    const { setPermissions } = useAppStore()
    const listRef = useRef<FlatList<Slide>>(null)
    const [index, setIndex] = useState(0)

    const pedirLocalizacao = async () => {
        try {
            await Location.requestForegroundPermissionsAsync()
        } catch {}
    }

    const pedirNotificacoes = async () => {
        try {
            if (!Device.isDevice) return
            if (Platform.OS === "android") {
                await Notifications.setNotificationChannelAsync("default", {
                    name: "default",
                    importance: Notifications.AndroidImportance.MAX,
                    vibrationPattern: [0, 250, 250, 250],
                    lightColor: colors.primary,
                })
                PermissionsAndroid.request(PermissionsAndroid.PERMISSIONS.POST_NOTIFICATIONS)
                await Notifications.requestPermissionsAsync()
            }
        } catch {}
    }

    const slides: Slide[] = [
        {
            key: "intro",
            icon: Flame,
            title: "A maneira mais fácil de pedir gás",
            desc: "Peça seu gás de cozinha em poucos toques e receba em casa, sem complicação.",
            cta: "Começar",
        },
        {
            key: "local",
            icon: MapPin,
            title: "Localização automática",
            desc: "Com a localização ativada, o gás chega mais rápido e sem erro de endereço.",
            cta: "Permitir e continuar",
            onEnter: pedirLocalizacao,
        },
        {
            key: "notif",
            icon: BellRing,
            title: "Acompanhe em tempo real",
            desc: "Receba avisos do status do pedido e saiba exatamente quando o gás vai chegar.",
            cta: "Ativar e concluir",
            onEnter: pedirNotificacoes,
        },
    ]

    const irPara = (i: number) => {
        listRef.current?.scrollToIndex({ index: i, animated: true })
    }

    const avancar = () => {
        const slide = slides[index]
        // Pede a permissão associada ao slide ATUAL ao avançar dele.
        slide.onEnter?.()

        if (index < slides.length - 1) {
            irPara(index + 1)
        } else {
            setPermissions(true)
            router.replace("/login")
        }
    }

    const onScroll = (e: NativeSyntheticEvent<NativeScrollEvent>) => {
        const i = Math.round(e.nativeEvent.contentOffset.x / width)
        if (i !== index) setIndex(i)
    }

    const renderItem = ({ item }: { item: Slide }) => {
        const Icon = item.icon
        return (
            <View style={[styles.slide, { paddingTop: insets.top + 40 }]}>
                <Animated.View entering={FadeIn.duration(500)} style={styles.illustration}>
                    <View style={styles.iconOuter}>
                        <View style={styles.iconInner}>
                            <Icon size={64} color={colors.primary} strokeWidth={1.8} />
                        </View>
                    </View>
                </Animated.View>

                <Animated.View entering={FadeInDown.delay(120).duration(500)} style={styles.texts}>
                    <Text style={styles.title}>{item.title}</Text>
                    <Text style={styles.desc}>{item.desc}</Text>
                </Animated.View>
            </View>
        )
    }

    return (
        <View style={styles.screen}>
            <FlatList
                ref={listRef}
                data={slides}
                keyExtractor={(s) => s.key}
                renderItem={renderItem}
                horizontal
                pagingEnabled
                showsHorizontalScrollIndicator={false}
                onMomentumScrollEnd={onScroll}
                scrollEventThrottle={16}
                getItemLayout={(_, i) => ({ length: width, offset: width * i, index: i })}
            />

            <View style={[styles.footer, { paddingBottom: insets.bottom + 20 }]}>
                <View style={styles.dots}>
                    {slides.map((_, i) => (
                        <View key={i} style={[styles.dot, i === index && styles.dotActive]} />
                    ))}
                </View>
                <Button title={slides[index].cta} uppercase={false} onPress={avancar} />
                {index < slides.length - 1 && (
                    <Text
                        style={styles.skip}
                        onPress={() => {
                            setPermissions(true)
                            router.replace("/login")
                        }}
                    >
                        Pular
                    </Text>
                )}
            </View>
        </View>
    )
}

const styles = StyleSheet.create({
    screen: { flex: 1, backgroundColor: colors.background },
    slide: {
        width,
        flex: 1,
        alignItems: "center",
        paddingHorizontal: 28,
    },
    illustration: { alignItems: "center", justifyContent: "center", marginTop: 20 },
    iconOuter: {
        width: 200,
        height: 200,
        borderRadius: 100,
        backgroundColor: colors.primaryMuted,
        alignItems: "center",
        justifyContent: "center",
    },
    iconInner: {
        width: 132,
        height: 132,
        borderRadius: 66,
        backgroundColor: colors.surface,
        alignItems: "center",
        justifyContent: "center",
    },
    texts: { alignItems: "center", marginTop: 44 },
    title: {
        fontSize: fontSize.xl,
        color: colors.text,
        textAlign: "center",
        ...fontStyle.bold,
    },
    desc: {
        fontSize: fontSize.sm,
        color: colors.textMuted,
        textAlign: "center",
        marginTop: 12,
        lineHeight: 24,
        ...fontStyle.regular,
    },
    footer: {
        paddingHorizontal: 24,
        gap: 16,
    },
    dots: {
        flexDirection: "row",
        justifyContent: "center",
        gap: 8,
        marginBottom: 4,
    },
    dot: {
        height: 8,
        width: 8,
        borderRadius: 999,
        backgroundColor: colors.border,
    },
    dotActive: {
        width: 26,
        backgroundColor: colors.primary,
    },
    skip: {
        textAlign: "center",
        fontSize: fontSize.sm,
        color: colors.textMuted,
        ...fontStyle.medium,
    },
})

export default Onboarding
