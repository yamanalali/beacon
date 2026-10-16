import { Link, usePage } from "@inertiajs/react";
import { useCallback, useEffect, useId, useRef, useState } from "react";
import { createPortal } from "react-dom";
import type { NavigationItem } from "../types/shared";
import { homeItem } from "./navigation";
import { QuickRecordButton, useQuickRecord } from "../Components/QuickRecord/QuickRecord";
import { homeLink, iconFor, navigationOrFallback, splitMobile } from "./navigationModel";

function NavLink({ item, className, onClick }: { item: NavigationItem; className: string; onClick?: () => void }) {
    return (
        <Link
            href={item.href}
            className={item.active ? `${className} is-active` : className}
            aria-current={item.active ? "page" : undefined}
            onClick={onClick}
        >
            <i className={`bi ${iconFor(item)}`} aria-hidden="true" />
            <span className={className === "app-tab" ? "app-tab__label" : undefined}>{item.label}</span>
        </Link>
    );
}

export function MobileBottomNavigation() {
    const { url, props } = usePage();
    // Links come from the server (`auth.navigation`); no role logic in the UI.
    const items = navigationOrFallback(props.auth.navigation, url, props.auth.can);
    const quick = useQuickRecord();
    // Centre «سجّل معاملة» tab takes one slot when available.
    const { tabs, more: moreItems } = splitMobile(items, quick ? 2 : 3);
    const primaryItems = tabs;
    const home = homeLink(items, url);
    const homeActive = home.active;
    const moreActive = moreItems.some((item) => item.active);
    const [open, setOpen] = useState(false);
    const moreButtonRef = useRef<HTMLButtonElement>(null);
    const closeButtonRef = useRef<HTMLButtonElement>(null);
    const dialogRef = useRef<HTMLDivElement>(null);
    const dialogId = useId();
    const titleId = useId();

    const close = useCallback(() => {
        setOpen(false);
        moreButtonRef.current?.focus();
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        closeButtonRef.current?.focus();
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === "Escape") {
                close();
                return;
            }

            if (event.key !== "Tab" || dialogRef.current === null) {
                return;
            }

            const focusable = dialogRef.current.querySelectorAll<HTMLElement>(
                'a[href], button:not([disabled]), [tabindex="0"]',
            );
            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (!first || !last) {
                return;
            }

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        const media = window.matchMedia("(min-width: 992px)");
        const onMedia = () => {
            if (media.matches) {
                setOpen(false);
            }
        };

        window.addEventListener("keydown", onKeyDown);
        media.addEventListener("change", onMedia);

        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener("keydown", onKeyDown);
            media.removeEventListener("change", onMedia);
        };
    }, [close, open]);

    useEffect(() => {
        setOpen(false);
    }, [url]);

    return (
        <nav className="app-bottom-nav d-lg-none" aria-label="التنقل السفلي">
            <ul className="app-bottom-nav__list">
                <li className="app-bottom-nav__item">
                    <Link
                        href={home.href}
                        className={homeActive ? "app-tab is-active" : "app-tab"}
                        aria-current={homeActive ? "page" : undefined}
                    >
                        <i
                            className={`bi ${homeItem.icon}`}
                            aria-hidden="true"
                        />
                        <span className="app-tab__label">{homeItem.label}</span>
                    </Link>
                </li>
                {primaryItems.slice(0, quick ? 1 : primaryItems.length).map((item) => (
                    <li key={item.key} className="app-bottom-nav__item">
                        <NavLink item={item} className="app-tab" />
                    </li>
                ))}
                {quick && (
                    <li className="app-bottom-nav__item">
                        <QuickRecordButton variant="tab" />
                    </li>
                )}
                {primaryItems.slice(quick ? 1 : primaryItems.length).map((item) => (
                    <li key={item.key} className="app-bottom-nav__item">
                        <NavLink item={item} className="app-tab" />
                    </li>
                ))}
                <li className="app-bottom-nav__item">
                    <button
                        ref={moreButtonRef}
                        type="button"
                        className={
                            open || moreActive ? "app-tab is-active" : "app-tab"
                        }
                        aria-expanded={open}
                        aria-controls={open ? dialogId : undefined}
                        onClick={() => setOpen(true)}
                    >
                        <i className="bi bi-grid" aria-hidden="true" />
                        <span className="app-tab__label">المزيد</span>
                    </button>
                </li>
            </ul>
            {open
                ? createPortal(
                      <>
                          <div
                              className="offcanvas-backdrop fade show app-sheet-backdrop"
                              onClick={close}
                              aria-hidden="true"
                          />
                          <div
                              ref={dialogRef}
                              id={dialogId}
                              className="offcanvas offcanvas-bottom show app-sheet"
                              role="dialog"
                              aria-modal="true"
                              aria-labelledby={titleId}
                              tabIndex={-1}
                          >
                              <div className="app-sheet__header">
                                  <h2 id={titleId} className="app-sheet__title">
                                      المزيد
                                  </h2>
                                  <button
                                      ref={closeButtonRef}
                                      type="button"
                                      className="app-icon-button"
                                      aria-label="إغلاق"
                                      onClick={close}
                                  >
                                      <i
                                          className="bi bi-x-lg"
                                          aria-hidden="true"
                                      />
                                  </button>
                              </div>
                              <ul className="app-sheet__list">
                                  {moreItems.map((item) => (
                                      <li key={item.key}>
                                          <NavLink item={item} className="app-side-link" onClick={close} />
                                      </li>
                                  ))}
                              </ul>
                          </div>
                      </>,
                      document.body,
                  )
                : null}
        </nav>
    );
}
