import { useEffect, useRef, useState } from "react"

const useTimer = (defaultTime: number): [number, React.Dispatch<React.SetStateAction<number>>] => {
    const [timer, setTimer] = useState(defaultTime)

    useEffect(() => {
        if (timer <= 0) return

        const interval = setInterval(() => {
            setTimer((cur) => {
                if (cur <= 1) return 0

                return cur - 1
            })
        }, 1000)

        return () => clearInterval(interval)
    }, [timer])

    return [timer, setTimer]
}

export default useTimer
