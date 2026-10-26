import type { ReactNode } from 'react';

type IconName = 'today' | 'history' | 'foods' | 'recipes' | 'account' | 'signOut';

export default function SidebarIcon({ name }: { name: IconName }) {
    const paths: Record<IconName, ReactNode> = {
        today: (
            <>
                <circle cx="12" cy="12" r="4" />
                <path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
            </>
        ),
        history: (
            <>
                <rect x="3" y="5" width="18" height="16" rx="2" />
                <path d="M3 10h18M8 3v4m8-4v4" />
            </>
        ),
        foods: (
            <>
                <path d="M3 11h18a9 9 0 0 1-18 0Z" />
                <path d="M9 7c0-2 1-3 2-3m3 3c0-2 1-3 2-3" />
            </>
        ),
        recipes: (
            <>
                <path d="M4 4h13a3 3 0 0 1 3 3v13H7a3 3 0 0 1-3-3V4Z" />
                <path d="M4 17a3 3 0 0 1 3-3h13M9 8h6" />
            </>
        ),
        account: (
            <>
                <circle cx="12" cy="8" r="4" />
                <path d="M4 21a8 8 0 0 1 16 0" />
            </>
        ),
        signOut: (
            <>
                <path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5" />
                <path d="M14 8l4 4-4 4m4-4H9" />
            </>
        ),
    };

    return (
        <svg
            className="sidebar-icon"
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
            {paths[name]}
        </svg>
    );
}
