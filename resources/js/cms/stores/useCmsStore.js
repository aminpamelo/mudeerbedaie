import { create } from 'zustand';

const useCmsStore = create((set) => ({
    sidebarOpen: false,
    toggleSidebar: () => set((state) => ({ sidebarOpen: !state.sidebarOpen })),
}));

export default useCmsStore;
