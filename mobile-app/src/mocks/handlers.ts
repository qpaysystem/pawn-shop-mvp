import type { LoginRequest, LoginResponse } from '@/src/types/auth';
import type { Client, ClientSearchResult, CreateClientPayload } from '@/src/types/client';
import type { CatalogItem, Store } from '@/src/types/item';
import type {
  CreatePawnContractPayload,
  PaginatedResponse,
  PawnContract,
} from '@/src/types/pawn';
import {
  MOCK_BRANDS,
  MOCK_CATEGORIES,
  MOCK_CLIENTS,
  MOCK_PAWNS,
  MOCK_STATUSES,
  MOCK_STORAGE,
  MOCK_STORES,
  MOCK_USER,
  createMockPawnContract,
} from './data';

const delay = (ms = 400) => new Promise((r) => setTimeout(r, ms));

export const mockApi = {
  async login(body: LoginRequest): Promise<LoginResponse> {
    await delay();
    if (body.email === 'demo@lombard.local' && body.password === 'demo') {
      return { token: 'mock-token-demo', user: MOCK_USER };
    }
    if (body.email === MOCK_USER.email && body.password === 'password') {
      return { token: 'mock-token-appraiser', user: MOCK_USER };
    }
    throw new Error('Неверный email или пароль (mock: demo@lombard.local / demo)');
  },

  async me(): Promise<typeof MOCK_USER> {
    await delay(200);
    return MOCK_USER;
  },

  async stores(): Promise<Store[]> {
    await delay(200);
    return MOCK_STORES;
  },

  async categories(): Promise<CatalogItem[]> {
    await delay(200);
    return MOCK_CATEGORIES;
  },

  async brands(): Promise<CatalogItem[]> {
    await delay(200);
    return MOCK_BRANDS;
  },

  async itemStatuses(): Promise<CatalogItem[]> {
    await delay(200);
    return MOCK_STATUSES;
  },

  async storageLocations(storeId: number) {
    await delay(200);
    return MOCK_STORAGE.filter((s) => s.store_id === storeId);
  },

  async searchClients(q: string): Promise<ClientSearchResult[]> {
    await delay();
    const trimmed = q.trim();
    const lower = trimmed.toLowerCase();
    const digits = trimmed.replace(/\D/g, '');
    return MOCK_CLIENTS.filter((c) => {
      const phoneDigits = c.phone.replace(/\D/g, '');
      return (
        c.full_name.toLowerCase().includes(lower) ||
        (digits.length >= 3 &&
          (phoneDigits.includes(digits) || c.phone.includes(trimmed)))
      );
    });
  },

  async listClients(params: {
    q?: string;
    blacklist?: boolean;
    page?: number;
  }): Promise<PaginatedResponse<Client>> {
    await delay();
    let rows = MOCK_CLIENTS.map((c) => ({
      ...c,
      client_type: 'individual' as const,
      email: null,
      passport_data: null,
      blacklist_flag: false,
    }));
    if (params.q) {
      const lower = params.q.toLowerCase();
      rows = rows.filter(
        (c) =>
          c.full_name.toLowerCase().includes(lower) ||
          c.phone.includes(params.q!.replace(/\D/g, '')),
      );
    }
    if (params.blacklist) {
      rows = rows.filter((c) => c.blacklist_flag);
    }
    return {
      data: rows,
      meta: { current_page: 1, last_page: 1, per_page: 20, total: rows.length },
    };
  },

  async getClient(id: number): Promise<Client> {
    await delay();
    const found = MOCK_CLIENTS.find((c) => c.id === id);
    if (!found) throw new Error('Client not found');
    return {
      ...found,
      client_type: 'individual',
      email: null,
      passport_data: null,
      blacklist_flag: false,
      active_pawn_contracts_count: 1,
    };
  },

  async createClient(payload: CreateClientPayload): Promise<Client> {
    await delay();
    const id = 200 + MOCK_CLIENTS.length;
    const fullName = [payload.last_name, payload.first_name, payload.patronymic]
      .filter(Boolean)
      .join(' ');
    const row: ClientSearchResult = {
      id,
      full_name: fullName,
      last_name: payload.last_name,
      first_name: payload.first_name,
      patronymic: payload.patronymic ?? null,
      phone: payload.phone,
    };
    MOCK_CLIENTS.push(row);
    return {
      ...row,
      client_type: 'individual',
      email: payload.email ?? null,
      passport_data: payload.passport_data ?? null,
      blacklist_flag: false,
    };
  },

  async listPawns(params: {
    status?: string;
    q?: string;
  }): Promise<PaginatedResponse<PawnContract>> {
    await delay();
    let rows = [...MOCK_PAWNS];
    if (params.status && params.status !== 'all') {
      rows = rows.filter((p) => p.computed_status === params.status);
    }
    if (params.q) {
      const q = params.q.toLowerCase();
      rows = rows.filter(
        (p) =>
          p.contract_number.toLowerCase().includes(q) ||
          p.client?.full_name.toLowerCase().includes(q),
      );
    }
    return { data: rows, meta: { current_page: 1, last_page: 1, per_page: 20, total: rows.length } };
  },

  async getPawn(id: number): Promise<PawnContract> {
    await delay();
    const found = MOCK_PAWNS.find((p) => p.id === id);
    if (!found) throw new Error('Договор не найден');
    return found;
  },

  async createPawn(payload: CreatePawnContractPayload): Promise<PawnContract> {
    await delay(800);
    const num = `L-2026-${String(MOCK_PAWNS.length + 1).padStart(5, '0')}`;
    const loan = payload.loan;
    const buyback =
      loan.loan_amount + (loan.loan_amount * loan.loan_percent) / 100;
    const contract = createMockPawnContract({
      contract_number: num,
      store_id: payload.store_id,
      client_id: payload.client_id ?? 101,
      loan_amount: loan.loan_amount.toFixed(2),
      loan_percent: loan.loan_percent.toFixed(2),
      loan_date: loan.loan_date,
      expiry_date: loan.expiry_date,
      buyback_amount: buyback.toFixed(2),
      redemption_amount: buyback.toFixed(2),
      item: {
        id: 999,
        name: payload.item.name,
        description: payload.item.description ?? null,
        barcode: `I${Date.now()}`,
        category_id: payload.item.category_id ?? null,
        brand_id: payload.item.brand_id ?? null,
        status_id: payload.item.status_id,
        status_name: 'Принят в ломбард',
        storage_location_id: payload.item.storage_location_id ?? null,
        store_id: payload.store_id,
        photos: [],
        initial_price: String(payload.item.initial_price ?? 0),
        current_price: String(payload.item.current_price ?? 0),
      },
    });
    MOCK_PAWNS.unshift(contract);
    return contract;
  },

  async redeemPawn(id: number): Promise<PawnContract> {
    await delay(500);
    const idx = MOCK_PAWNS.findIndex((p) => p.id === id);
    if (idx < 0) throw new Error('Договор не найден');
    MOCK_PAWNS[idx] = {
      ...MOCK_PAWNS[idx],
      is_redeemed: true,
      redeemed_at: new Date().toISOString(),
      computed_status: 'redeemed',
    };
    return MOCK_PAWNS[idx];
  },

  async payInterestPawn(id: number, extendDays: number): Promise<PawnContract> {
    await delay(500);
    const idx = MOCK_PAWNS.findIndex((p) => p.id === id);
    if (idx < 0) throw new Error('Договор не найден');
    const current = MOCK_PAWNS[idx];
    const base = current.expiry_date ? new Date(current.expiry_date) : new Date();
    base.setDate(base.getDate() + extendDays);
    MOCK_PAWNS[idx] = {
      ...current,
      expiry_date: base.toISOString().slice(0, 10),
      computed_status: 'active',
    };
    return MOCK_PAWNS[idx];
  },
};
