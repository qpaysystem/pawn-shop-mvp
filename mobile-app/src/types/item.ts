/** Mirrors App\Models\Item + related catalogs. */
export interface ItemPhoto {
  url: string;
  path: string;
}

export interface Item {
  id: number;
  name: string;
  description: string | null;
  barcode: string;
  category_id: number | null;
  brand_id: number | null;
  status_id: number | null;
  status_name?: string;
  storage_location_id: number | null;
  store_id: number;
  photos: ItemPhoto[];
  initial_price: string | null;
  current_price: string | null;
  metal?: string | null;
  sample?: string | null;
  weight_grams?: string | null;
}

export interface CatalogItem {
  id: number;
  name: string;
  color?: string | null;
}

export type Brand = CatalogItem;

export interface Store extends CatalogItem {
  address?: string | null;
  phone?: string | null;
  is_active?: boolean;
}

export interface StorageLocation extends CatalogItem {
  store_id: number;
}
