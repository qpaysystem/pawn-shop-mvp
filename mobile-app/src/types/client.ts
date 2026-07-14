/** Mirrors App\Models\Client (pawn-shop-mvp). */
export type ClientType = 'individual' | 'legal';

export interface Client {
  id: number;
  client_type: ClientType;
  full_name: string;
  last_name: string | null;
  first_name: string | null;
  patronymic: string | null;
  phone: string;
  email: string | null;
  passport_data: string | null;
  blacklist_flag: boolean;
  lmb_passport_issued_by?: string | null;
  lmb_passport_issued_at?: string | null;
  lmb_registration_address?: string | null;
}

export interface ClientSearchResult {
  id: number;
  full_name: string;
  last_name: string | null;
  first_name: string | null;
  patronymic: string | null;
  phone: string;
  email?: string | null;
}

export interface CreateClientPayload {
  client_type?: ClientType;
  last_name: string;
  first_name: string;
  patronymic?: string;
  phone: string;
  email?: string;
  passport_data?: string;
}

export interface ClientListParams {
  q?: string;
  blacklist?: boolean;
  page?: number;
}

export interface ClientDetail extends Client {
  legal_name?: string | null;
  inn?: string | null;
  notes?: string | null;
  active_pawn_contracts_count?: number;
}

export interface PassportParseResult {
  last_name?: string;
  first_name?: string;
  patronymic?: string;
  passport_series?: string;
  passport_number?: string;
  passport_data?: string;
  birth_date?: string;
  issued_by?: string;
  issued_at?: string;
}
