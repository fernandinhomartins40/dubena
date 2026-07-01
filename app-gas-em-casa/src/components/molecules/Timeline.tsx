import { colors, fontSize, fontStyle, screenPadding } from "@/styles/theme"
import { Animated, StyleSheet, Text, View } from "react-native"
import { Check } from "lucide-react-native"
import { TimelineStep } from "@/types/types"
import { useEffect } from "react"

type Props = {
    tracklist: TimelineStep[]
}

const Timeline = ({ tracklist }: Props) => {
    return tracklist.map((track, idx) => (
        <TimelineItem key={`track_${idx}`} track={track} idx={idx} length={tracklist.length} />
    ))
}

type ItemProps = {
    track: TimelineStep
    idx: number
    length: number
}

const TimelineItem = ({ track, idx, length }: ItemProps) => {
    const isLast = idx + 1 === length
    const textColor = track.isCurrent ? colors.primary : "#000"
    let lineColor = colors.primary

    if (isLast) {
        lineColor = "#FFF"
    } else if (track.isCurrent || !track.completed) {
        lineColor = colors.primaryMuted
    }

    const lineWidth = new Animated.Value(track.completed ? 1 : 0)
    const bulletScale = new Animated.Value(1)

    useEffect(() => {
        if (track.isCurrent) {
            Animated.timing(lineWidth, {
                toValue: 1,
                duration: 100,
                useNativeDriver: false,
            }).start()

            Animated.spring(bulletScale, {
                toValue: 1.2,
                friction: 3,
                tension: 120,
                useNativeDriver: true,
            }).start()
        } else if (track.completed) {
            Animated.spring(bulletScale, {
                toValue: 1,
                friction: 3,
                tension: 120,
                useNativeDriver: true,
            }).start()
        }
    }, [track.completed, track.isCurrent])

    return (
        <View style={{ flexDirection: "row", gap: 12 }}>
            <View
                style={{
                    flexDirection: "column",
                    alignItems: "center",
                }}
            >
                <BulletPoint track={track} idx={idx} bulletScale={bulletScale} />
                <Animated.View
                    style={{
                        backgroundColor: lineColor,
                        height: 70,
                        width: lineWidth.interpolate({
                            inputRange: [0, 1],
                            outputRange: [0, 3],
                        }),
                        zIndex: 1,
                    }}
                ></Animated.View>
            </View>
            <View style={styles.textCol}>
                <Text style={{ fontSize: fontSize.sm, color: textColor, ...fontStyle.semiBold }}>
                    {track.title}
                </Text>
                <Text
                    style={{
                        fontSize: 13,
                        color: colors.textMuted,
                        marginTop: 2,
                        lineHeight: 18,
                        ...fontStyle.regular,
                    }}
                >
                    {track.description}
                </Text>
            </View>
        </View>
    )
}

type BulletProps = {
    track: TimelineStep
    idx: number
    bulletScale: Animated.Value
}

const BulletPoint = ({ track, idx, bulletScale }: BulletProps) => {
    if (track.completed) {
        return (
            <View
                style={[
                    styles.bulletPoint,
                    {
                        backgroundColor: colors.primary,
                    },
                ]}
            >
                <Check size={15} color="white" strokeWidth={3} />
            </View>
        )
    }

    if (track.isCurrent) {
        return (
            <Animated.View
                style={[
                    styles.bulletPoint,
                    {
                        borderWidth: 2,
                        borderColor: colors.primary,
                        transform: [{ scale: bulletScale }],
                    },
                ]}
            >
                <View
                    style={{
                        backgroundColor: colors.primary,
                        width: 12,
                        height: 12,
                        borderRadius: 30,
                    }}
                ></View>
            </Animated.View>
        )
    }

    return (
        <View
            style={[
                styles.bulletPoint,
                {
                    borderWidth: 2,
                    borderColor: colors.primaryMuted,
                },
            ]}
        >
            <Text style={{ color: colors.primaryMuted }}>{idx + 1}</Text>
        </View>
    )
}

const styles = StyleSheet.create({
    bulletPoint: {
        height: 26,
        width: 26,
        borderRadius: 999,
        alignItems: "center",
        justifyContent: "center",
        zIndex: 2,
    },
    textCol: {
        flex: 1,
        paddingTop: 2,
        paddingBottom: 8,
    },
})

export default Timeline
