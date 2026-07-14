import type { AuthUser } from '@/src/types/auth';
import type { ClientSearchResult } from '@/src/types/client';
import type { Brand, CatalogItem, StorageLocation, Store } from '@/src/types/item';
import type { PawnContract } from '@/src/types/pawn';

export const MOCK_USER: AuthUser = {
  id: 2,
  name: 'Оценщик Тест',
  email: 'appraiser@example.com',
  role: 'appraiser',
  store_id: 1,
  store_name: 'ДК Ломбард',
  permissions: {
    can_create_contracts: true,
    can_process_sales: false,
    can_manage_storage: false,
  },
};

export const MOCK_STORES: Store[] = [
  { id: 1, name: 'ДК Ломбард', address: 'г. Новосибирск', is_active: true },
];

export const MOCK_CATEGORIES: CatalogItem[] = [
  { id: 1, name: 'Ювелирные изделия' },
  { id: 2, name: 'Техника' },
  { id: 3, name: 'Часы' },
];

export const MOCK_BRANDS: Brand[] = [
  { id: 1, name: 'Apple' },
  { id: 2, name: 'Samsung' },
];

export const MOCK_STATUSES: CatalogItem[] = [
  { id: 1, name: 'Принят в ломбард', color: '#17a2b8' },
  { id: 2, name: 'На витрине', color: '#28a745' },
  { id: 4, name: 'Выкуплен', color: '#20c997' },
];

export const MOCK_STORAGE: StorageLocation[] = [
  { id: 1, name: 'Сейф 1', store_id: 1 },
  { id: 2, name: 'Витрина А', store_id: 1 },
];

export const MOCK_CLIENTS: ClientSearchResult[] = [
  {
    id: 101,
    full_name: 'Иванов Иван Иванович',
    last_name: 'Иванов',
    first_name: 'Иван',
    patronymic: 'Иванович',
    phone: '+79001234567',
  },
  {
    id: 102,
    full_name: 'Петрова Анна Сергеевна',
    last_name: 'Петрова',
    first_name: 'Анна',
    patronymic: 'Сергеевна',
    phone: '+79007654321',
  },
];

let nextPawnId = 1000;

export function createMockPawnContract(
  partial: Partial<PawnContract> & Pick<PawnContract, 'contract_number'>,
): PawnContract {
  const id = nextPawnId++;
  return {
    id,
    contract_number: partial.contract_number,
    client_id: partial.client_id ?? 101,
    item_id: partial.item_id ?? id,
    store_id: partial.store_id ?? 1,
    appraiser_id: 2,
    loan_amount: partial.loan_amount ?? '5000.00',
    loan_percent: partial.loan_percent ?? '20.00',
    loan_date: partial.loan_date ?? new Date().toISOString().slice(0, 10),
    expiry_date:
      partial.expiry_date ??
      new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
    buyback_amount: partial.buyback_amount ?? '6000.00',
    redemption_amount: partial.redemption_amount ?? '6000.00',
    is_redeemed: false,
    redeemed_at: null,
    computed_status: partial.computed_status ?? 'active',
    client: partial.client,
    item: partial.item,
  };
}

export const MOCK_PAWNS: PawnContract[] = [
  createMockPawnContract({
    contract_number: 'L-2026-00001',
    computed_status: 'active',
    client: {
      id: 101,
      client_type: 'individual',
      full_name: 'Иванов Иван Иванович',
      last_name: 'Иванов',
      first_name: 'Иван',
      patronymic: 'Иванович',
      phone: '+79001234567',
      email: null,
      passport_data: '50 19 961613',
      blacklist_flag: false,
    },
    item: {
      id: 1,
      name: 'Кольцо золото 585',
      description: null,
      barcode: 'I2026042000001',
      category_id: 1,
      brand_id: null,
      status_id: 1,
      status_name: 'Принят в ломбард',
      storage_location_id: 1,
      store_id: 1,
      photos: [],
      initial_price: '15000.00',
      current_price: '15000.00',
    },
  }),
  createMockPawnContract({
    contract_number: 'L-2026-00002',
    computed_status: 'overdue',
    loan_date: '2026-01-01',
    expiry_date: '2026-02-01',
  }),
];
