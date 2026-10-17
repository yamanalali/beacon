import { Head, usePage } from "@inertiajs/react";
import { useEffect } from "react";
import type React from "react";
import { DesktopSidebar } from "./DesktopSidebar";
import { DesktopTopbar } from "./DesktopTopbar";
import { MobileAppHeader } from "./MobileAppHeader";
import { MobileBottomNavigation } from "./MobileBottomNavigation";
import { ConnectionStatus } from "../Components/Feedback/ConnectionStatus";

export default function AppLayout({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    const { locale, dir, flash } = usePage().props;

    useEffect(() => {
        document.documentElement.lang = locale;
        document.documentElement.dir = dir;
    }, [dir, locale]);

    return (
        <div className="app-shell d-flex flex-column flex-lg-row min-vh-100">
            <Head title={`${title} | بيكون`} />
            <a className="app-skip" href="#main-content">
                تخطى إلى المحتوى
            </a>
            <DesktopSidebar />
            <div className="app-frame d-flex flex-column flex-grow-1">
                <MobileAppHeader title={title} />
                <DesktopTopbar title={title} />
                <ConnectionStatus />
                <main
                    id="main-content"
                    className="app-content flex-grow-1"
                    tabIndex={-1}
                >
                    {flash?.success ? (
                        <div className="app-page pb-0">
                            <div
                                className="alert alert-success mb-0"
                                role="status"
                            >
                                <i
                                    className="bi bi-check-circle me-2"
                                    aria-hidden="true"
                                />
                                {flash.success}
                            </div>
                        </div>
                    ) : null}
                    {flash?.warning ? (
                        <div className="app-page pb-0">
                            <div className="alert alert-warning mb-0 d-flex gap-2 align-items-start" role="status" data-testid="flash-warning">
                                <i className="bi bi-exclamation-triangle" aria-hidden="true" />
                                <span>{flash.warning}</span>
                            </div>
                        </div>
                    ) : null}
                    {children}
                </main>
            </div>
            <MobileBottomNavigation />
        </div>
    );
}
