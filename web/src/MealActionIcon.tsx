export default function MealActionIcon({ name }: { name: 'edit' | 'delete' }) {
    return (
        <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {name === 'edit' ? (
                <path d="M4 20h4L19 9l-4-4L4 16v4ZM13.5 6.5l4 4" />
            ) : (
                <>
                    <path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" />
                </>
            )}
        </svg>
    );
}
