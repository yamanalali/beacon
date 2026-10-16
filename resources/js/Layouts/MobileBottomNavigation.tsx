import { Link, usePage } from "@inertiajs/react";
import { useCallback, useEffect, useId, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { SoonButton } from "./SoonButton";
import {
    accountItem,
    homeItem,
    isAccountPath,
    isHomePath,
    isNavItemActive,
    workspaceGroups,
} from "./navigation";

export function MobileBottomNavigation() {
    const { url, props } = usePage();
    const enterprise = props.enterprise as { public_id: string } | undefined;
    const groups = workspaceGroups(
        props.auth.user?.role,
        enterprise?.public_id,
    );
    const allItems = groups.flatMap((group) => group.items);
    const primaryItems =
        props.auth.user?.role === "technical_admin"
            ? allItems.slice(0, 3)
            : props.auth.user?.role === "mfi_user"
              ? allItems
              : allItems.filter((item) =>
                    ["transactions", "reports", "enterprises"].includes(
                        item.key,
                    ),
                );
    const moreItems = allItems.filter(
        (item) => !primaryItems.some((primary) => primary.key === item.key),
    );
    const homeActive = isHomePath(url);
    const accountActive = isAccountPath(url);
    const moreActive =
        accountActive || moreItems.some((item) => isNavItemActive(item, url));
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
                        href="/"
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
                {primaryItems.map((item) => (
                    <li key={item.key} className="app-bottom-nav__item">
                        <SoonButton
                            item={item}
                            className="app-tab"
                            soonClassName="app-tab__soon"
                        />
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
                                          <SoonButton
                                              item={item}
                                              className="app-side-link"
                                          />
                                      </li>
                                  ))}
                                  <li>
                                      <Link
                                          href="/account"
                                          className={
                                              accountActive
                                                  ? "app-side-link is-active"
                                                  : "app-side-link"
                                          }
                                          aria-current={
                                              accountActive ? "page" : undefined
                                          }
                                          onClick={close}
                                      >
                                          <i
                                              className={`bi ${accountItem.icon}`}
                                              aria-hidden="true"
                                          />
                                          <span>{accountItem.label}</span>
                                      </Link>
                                  </li>
                              </ul>
                          </div>
                      </>,
                      document.body,
                  )
                : null}
        </nav>
    );
}
