import { env } from '@/src/config/env';
import { mockApi } from '@/src/mocks/handlers';
import type { CatalogItem, StorageLocation, Store } from '@/src/types/item';
import { apiRequest } from './http';

/** TODO(backend): GET /api/v1/stores, item-categories, brands, item-statuses, storage-locations */
export async function fetchStores(token: string): Promise<Store[]> {
  if (env.useMockData) return mockApi.stores();
  return apiRequest<Store[]>('/stores', { token });
}

export async function fetchCategories(token: string): Promise<CatalogItem[]> {
  if (env.useMockData) return mockApi.categories();
  return apiRequest<CatalogItem[]>('/item-categories', { token });
}

export async function fetchBrands(token: string): Promise<CatalogItem[]> {
  if (env.useMockData) return mockApi.brands();
  return apiRequest<CatalogItem[]>('/brands', { token });
}

export async function fetchItemStatuses(token: string): Promise<CatalogItem[]> {
  if (env.useMockData) return mockApi.itemStatuses();
  return apiRequest<CatalogItem[]>('/item-statuses', { token });
}

export async function fetchStorageLocations(
  token: string,
  storeId: number,
): Promise<StorageLocation[]> {
  if (env.useMockData) return mockApi.storageLocations(storeId);
  return apiRequest<StorageLocation[]>(
    `/storage-locations?store_id=${storeId}`,
    { token },
  );
}
