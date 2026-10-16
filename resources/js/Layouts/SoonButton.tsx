import type { NavItem } from "./navigation";

type SoonButtonProps = {
    item: NavItem;
    className: string;
    soonClassName?: string;
};

export function SoonButton({ item, className, soonClassName = "app-soon" }: SoonButtonProps) {
    return (
        <button
            type="button"
            className={className}
            aria-disabled="true"
            onClick={(event) => {
                event.preventDefault();
            }}
        >
            <i className={`bi ${item.icon}`} aria-hidden="true" />
            <span>{item.label}</span>
            <span className={soonClassName}>قريباً</span>
        </button>
    );
}
