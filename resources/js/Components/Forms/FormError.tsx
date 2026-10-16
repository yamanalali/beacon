export function FormError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <p className="mb-3" role="alert" style={{ color: 'var(--app-danger)', fontSize: '0.875rem', lineHeight: 1.5 }}>
            {message}
        </p>
    );
}
