import { create } from 'zustand';
import {
  login as apiLogin,
  logout as apiLogout,
  getToken,
  clearToken,
  setToken,
  getBaseUrl,
  setBaseUrl,
  getApiClient,
} from '../api/client';
import * as SecureStore from 'expo-secure-store';
import * as LocalAuthentication from 'expo-local-authentication';
import type { User } from '../types';

const BIOMETRIC_KEY = 'crm_biometric_enabled';

interface AuthState {
  user: User | null;
  token: string | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  serverUrl: string;
  biometricEnabled: boolean;
  biometricAvailable: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  checkAuth: () => Promise<void>;
  refreshToken: () => Promise<void>;
  setBiometric: (enabled: boolean) => Promise<void>;
  checkBiometric: () => Promise<void>;
  authenticateWithBiometric: () => Promise<boolean>;
  updateServerUrl: (url: string) => Promise<void>;
}

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  token: null,
  isLoading: true,
  isAuthenticated: false,
  serverUrl: '',
  biometricEnabled: false,
  biometricAvailable: false,

  login: async (email: string, password: string) => {
    const token = await apiLogin(email, password);
    // Fetch user profile
    try {
      const client = getApiClient();
      const res = await client.get('/auth/user');
      set({ user: res.data.data || res.data, token, isAuthenticated: true, isLoading: false });
    } catch {
      set({ token, isAuthenticated: true, isLoading: false });
    }
  },

  logout: async () => {
    await apiLogout();
    set({ user: null, token: null, isAuthenticated: false });
  },

  checkAuth: async () => {
    const token = await getToken();
    const serverUrl = await getBaseUrl();
    const biometricEnabled = (await SecureStore.getItemAsync(BIOMETRIC_KEY)) === 'true';
    const biometricAvailable = await LocalAuthentication.hasHardwareAsync();

    if (token) {
      set({
        token,
        isAuthenticated: true,
        isLoading: false,
        serverUrl,
        biometricEnabled,
        biometricAvailable,
      });
    } else {
      set({
        isLoading: false,
        isAuthenticated: false,
        serverUrl,
        biometricEnabled,
        biometricAvailable,
      });
    }
  },

  refreshToken: async () => {
    try {
      const client = getApiClient();
      const res = await client.post('/auth/refresh');
      const newToken = res.data.token || res.data.data?.token;
      if (newToken) {
        await setToken(newToken);
        set({ token: newToken });
      }
    } catch {
      await clearToken();
      set({ token: null, isAuthenticated: false, user: null });
    }
  },

  setBiometric: async (enabled: boolean) => {
    await SecureStore.setItemAsync(BIOMETRIC_KEY, enabled ? 'true' : 'false');
    set({ biometricEnabled: enabled });
  },

  checkBiometric: async () => {
    const available = await LocalAuthentication.hasHardwareAsync();
    const enrolled = await LocalAuthentication.isEnrolledAsync();
    set({ biometricAvailable: available && enrolled });
  },

  authenticateWithBiometric: async () => {
    const result = await LocalAuthentication.authenticateAsync({
      promptMessage: 'Authenticate to access CRM',
      fallbackLabel: 'Use password',
      cancelLabel: 'Cancel',
    });
    return result.success;
  },

  updateServerUrl: async (url: string) => {
    const cleanUrl = url.replace(/\/+$/, '');
    await setBaseUrl(cleanUrl);
    set({ serverUrl: cleanUrl });
  },
}));
