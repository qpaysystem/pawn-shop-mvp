/**
 * Public env vars (EXPO_PUBLIC_*).
 * @see https://docs.expo.dev/guides/environment-variables/
 */

function resolveApiV1BaseUrl(raw?: string): string {
  const base = (raw ?? 'http://127.0.0.1:8000').replace(/\/$/, '');
  if (base.endsWith('/api/v1')) {
    return base;
  }
  return `${base}/api/v1`;
}

const apiV1Base = resolveApiV1BaseUrl(process.env.EXPO_PUBLIC_API_BASE_URL);

/** EXPO_PUBLIC_USE_MOCK_API=false → real Laravel auth. */
const mockAuthEnabled = process.env.EXPO_PUBLIC_USE_MOCK_API !== 'false';

/** EXPO_PUBLIC_USE_MOCK_DATA=false → залоги, клиенты, справочники с backend v1. */
const mockDataEnabled = process.env.EXPO_PUBLIC_USE_MOCK_DATA !== 'false';

export const env = {
  /** Host без пути API, напр. http://127.0.0.1:8000 */
  apiOrigin: apiV1Base.replace(/\/api\/v1$/, ''),
  /** Базовый URL mobile API v1 */
  apiBaseUrl: apiV1Base,
  /**
   * Auth: mock handlers vs POST/GET /api/v1/auth/*.
   * false when EXPO_PUBLIC_USE_MOCK_API=false.
   */
  useMockAuth: mockAuthEnabled,
  /**
   * Pledges, clients, catalogs — mock или /api/v1/* (см. EXPO_PUBLIC_USE_MOCK_DATA).
   */
  useMockData: mockDataEnabled,
  /** @deprecated Use useMockAuth / useMockData */
  get useMockApi(): boolean {
    return this.useMockAuth && this.useMockData;
  },
  appName: process.env.EXPO_PUBLIC_APP_NAME ?? 'Ломбард',
  apiTimeoutMs: Number(process.env.EXPO_PUBLIC_API_TIMEOUT_MS ?? 30_000),
};
