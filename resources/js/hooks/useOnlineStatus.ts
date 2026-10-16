import { useEffect, useState } from "react";

/** Browser connectivity signal. navigator.onLine can report true on captive/slow networks, so treat it as a hint only. */
export function useOnlineStatus(): boolean {
    const [online, setOnline] = useState<boolean>(typeof navigator === "undefined" ? true : navigator.onLine);

    useEffect(() => {
        const on = () => setOnline(true);
        const off = () => setOnline(false);
        window.addEventListener("online", on);
        window.addEventListener("offline", off);
        return () => {
            window.removeEventListener("online", on);
            window.removeEventListener("offline", off);
        };
    }, []);

    return online;
}