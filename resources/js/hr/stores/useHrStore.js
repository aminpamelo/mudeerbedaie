import { create } from 'zustand';

const THEME_KEY = 'hr-theme';

function getInitialTheme() {
    if (typeof window === 'undefined') {
        return 'light';
    }
    try {
        const stored = localStorage.getItem(THEME_KEY);
        if (stored === 'light' || stored === 'dark') {
            return stored;
        }
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    } catch {
        return 'light';
    }
}

function applyTheme(theme) {
    if (typeof document === 'undefined') {
        return;
    }
    const el = document.documentElement;
    el.classList.toggle('dark', theme === 'dark');
    el.style.colorScheme = theme;
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        meta.setAttribute('content', theme === 'dark' ? '#020617' : '#1e40af');
    }
}

const useHrStore = create((set, get) => ({
    sidebarOpen: false,
    toggleSidebar: () => set((state) => ({ sidebarOpen: !state.sidebarOpen })),

    theme: getInitialTheme(),
    setTheme: (theme) => {
        applyTheme(theme);
        try {
            localStorage.setItem(THEME_KEY, theme);
        } catch {
            /* ignore — private mode / storage disabled */
        }
        set({ theme });
    },
    toggleTheme: () => {
        const next = get().theme === 'dark' ? 'light' : 'dark';
        get().setTheme(next);
    },
}));

export default useHrStore;
