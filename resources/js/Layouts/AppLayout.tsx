import { Head, usePage } from "@inertiajs/react";
import { useEffect } from "react";
import type React from "react";
import { DesktopSidebar } from "./DesktopSidebar";
import { DesktopTopbar } from "./DesktopTopbar";
import { MobileAppHeader } from "./MobileAppHeader";
import { MobileBottomNavigation } from "./MobileBottomNavigation";

export default function AppLayout({ title, children }: { title: string; children: React.ReactNode }) {
    const { locale, dir } = usePage().props;

    useEffect(() => {
        document.documentElement.lang = locale;
        document.documentElement.dir = dir;
    }, [dir, locale]);

    return (
        <div className="app-shell d-flex flex-column flex-lg-row min-vh-100">
            <Head title="بيكون" />
            <a className="app-skip" href="#main-content">
                تخطى إلى المحتوى
            </a>
            <DesktopSidebar />
            <div className="app-frame d-flex flex-column flex-grow-1">
                <MobileAppHeader title={title} />
                <DesktopTopbar title={title} />
                <main id="main-content" className="app-content flex-grow-1">
                    {children}
                </main>
            </div>
            <MobileBottomNavigation />
        </div>
    );
}
