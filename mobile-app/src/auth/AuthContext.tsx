import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import { loginApi, logoutApi, meApi } from '@/src/api/auth';
import {
  fetchBrands,
  fetchCategories,
  fetchItemStatuses,
  fetchStorageLocations,
  fetchStores,
} from '@/src/api/catalogs';
import { env } from '@/src/config/env';
import type { AuthUser, LoginRequest } from '@/src/types/auth';
import type { CatalogItem, StorageLocation, Store } from '@/src/types/item';
import { isSessionExpiredError } from '@/src/utils/formatApiError';
import { clearToken, loadToken, saveToken } from './storage';

export interface CatalogCache {
  stores: Store[];
  categories: CatalogItem[];
  brands: CatalogItem[];
  statuses: CatalogItem[];
  storageByStore: Record<number, StorageLocation[]>;
}

const emptyCatalogs = (): CatalogCache => ({
  stores: [],
  categories: [],
  brands: [],
  statuses: [],
  storageByStore: {},
});

interface AuthContextValue {
  user: AuthUser | null;
  token: string | null;
  catalogs: CatalogCache | null;
  /** @deprecated use isRestoring */
  isLoading: boolean;
  /** Сессия поднимается в фоне — UI логина не блокируется */
  isRestoring: boolean;
  isAuthenticated: boolean;
  sessionExpired: boolean;
  login: (body: Omit<LoginRequest, 'device_name'>) => Promise<void>;
  logout: () => Promise<void>;
  refreshCatalogs: () => Promise<void>;
  clearSessionExpired: () => void;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

async function loadCatalogs(token: string, storeId: number | null): Promise<CatalogCache> {
  const [stores, categories, brands, statuses] = await Promise.all([
    fetchStores(token),
    fetchCategories(token),
    fetchBrands(token),
    fetchItemStatuses(token),
  ]);
  const storageByStore: Record<number, StorageLocation[]> = {};
  const sid = storeId ?? stores[0]?.id;
  if (sid) {
    storageByStore[sid] = await fetchStorageLocations(token, sid);
  }
  return { stores, categories, brands, statuses, storageByStore };
}

async function loadCatalogsAfterAuth(
  token: string,
  storeId: number | null,
): Promise<CatalogCache> {
  return loadCatalogs(token, storeId);
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [catalogs, setCatalogs] = useState<CatalogCache | null>(null);
  const [isRestoring, setIsRestoring] = useState(false);
  const [sessionExpired, setSessionExpired] = useState(false);

  const clearSessionExpired = useCallback(() => setSessionExpired(false), []);

  const refreshCatalogs = useCallback(async () => {
    if (!token || !user) return;
    const data = await loadCatalogs(token, user.store_id);
    setCatalogs(data);
  }, [token, user]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      setIsRestoring(true);
      try {
        const stored = await Promise.race([
          loadToken(),
          new Promise<null>((resolve) => setTimeout(() => resolve(null), 2500)),
        ]);
        if (!stored) {
          return;
        }
        const me = await Promise.race([
          meApi(stored),
          new Promise<never>((_, reject) =>
            setTimeout(() => reject(new Error('timeout')), 8000),
          ),
        ]);
        if (cancelled) return;
        setToken(stored);
        setUser(me);
        const cats = await loadCatalogsAfterAuth(stored, me.store_id);
        if (!cancelled) setCatalogs(cats);
      } catch (e) {
        await clearToken();
        if (!cancelled && isSessionExpiredError(e)) {
          setSessionExpired(true);
        }
        setToken(null);
        setUser(null);
        setCatalogs(null);
      } finally {
        if (!cancelled) setIsRestoring(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const login = useCallback(async (body: Omit<LoginRequest, 'device_name'>) => {
    const res = await loginApi({
      ...body,
      device_name: 'iphone-standalone',
    });
    await saveToken(res.token);
    setSessionExpired(false);
    setToken(res.token);
    setUser(res.user);
    // Не блокируем переход на главную — справочники подгрузятся в фоне
    void loadCatalogsAfterAuth(res.token, res.user.store_id)
      .then(setCatalogs)
      .catch(() => setCatalogs(emptyCatalogs()));
  }, []);

  const logout = useCallback(async () => {
    if (token) {
      try {
        await logoutApi(token);
      } catch {
        /* ignore — local session cleared anyway */
      }
    }
    await clearToken();
    setSessionExpired(false);
    setToken(null);
    setUser(null);
    setCatalogs(null);
  }, [token]);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      token,
      catalogs,
      isLoading: isRestoring,
      isRestoring,
      isAuthenticated: Boolean(token && user),
      sessionExpired,
      login,
      logout,
      refreshCatalogs,
      clearSessionExpired,
    }),
    [
      user,
      token,
      catalogs,
      isRestoring,
      sessionExpired,
      login,
      logout,
      refreshCatalogs,
      clearSessionExpired,
    ],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
