import type { Client } from './client';
import type { Item } from './item';

/** Computed on API; mirrors MOBILE_APP_SPEC. */
export type PawnComputedStatus = 'active' | 'overdue' | 'redeemed';

export interface PawnContract {
  id: number;
  contract_number: string;
  client_id: number;
  item_id: number;
  store_id: number;
  appraiser_id: number | null;
  loan_amount: string;
  loan_percent: string;
  loan_date: string;
  expiry_date: string;
  buyback_amount: string | null;
  redemption_amount?: string;
  is_redeemed: boolean;
  redeemed_at: string | null;
  computed_status: PawnComputedStatus;
  client?: Client;
  item?: Item;
}

export interface PawnContractListParams {
  store_id?: number;
  status?: PawnComputedStatus | 'all';
  q?: string;
  page?: number;
  per_page?: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface LoanTermsPayload {
  loan_amount: number;
  loan_percent: number;
  loan_date: string;
  expiry_date: string;
}

export interface ItemPayload {
  name: string;
  description?: string | null;
  category_id?: number | null;
  brand_id?: number | null;
  status_id: number;
  storage_location_id?: number | null;
  initial_price?: number | null;
  current_price?: number | null;
}

export type VisitPurpose = 'appraisal' | 'redemption' | 'non_target' | 'identification';

export interface CreatePawnContractPayload {
  store_id: number;
  visit_purpose?: VisitPurpose;
  client_id?: number | null;
  client?: {
    last_name: string;
    first_name: string;
    patronymic?: string;
    phone: string;
    passport_data?: string;
  };
  item: ItemPayload;
  loan: LoanTermsPayload;
}

export interface RedemptionSearchClient {
  id: number;
  full_name: string;
  phone: string;
  contracts: Array<{
    id: number;
    contract_number: string;
    item_name: string;
    loan_amount: number;
    loan_percent: number;
    buyback_amount: number;
    redemption_amount: number;
    expiry_date: string | null;
    store_name: string | null;
  }>;
}
