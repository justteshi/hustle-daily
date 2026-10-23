import * as SecureStore from 'expo-secure-store';
import { create } from 'zustand';

const tokenKey = 'hustle_daily_token';

type SessionState = {
    token: string | null;
    hydrated: boolean;
    hydrate: () => Promise<void>;
    save: (token: string) => Promise<void>;
    clear: () => Promise<void>;
};

export const useSession = create<SessionState>((set) => ({
    token: null,
    hydrated: false,
    hydrate: async () => {
        try {
            set({ token: await SecureStore.getItemAsync(tokenKey), hydrated: true });
        } catch {
            set({ token: null, hydrated: true });
        }
    },
    save: async (token) => {
        await SecureStore.setItemAsync(tokenKey, token);
        set({ token });
    },
    clear: async () => {
        await SecureStore.deleteItemAsync(tokenKey);
        set({ token: null });
    },
}));
