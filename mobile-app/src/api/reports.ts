import { apiRequest } from './http';

export interface ProfitStoreRow {
  store_name: string;
  count: number;
  profit: number;
  loan_amount?: number;
  buyback_amount?: number;
  revenue?: number;
  cost?: number;
}

export interface ProfitReportResponse {
  date_from: string;
  date_to: string;
  pawn: {
    totals: {
      count: number;
      loan_amount: number;
      buyback_amount: number;
      profit: number;
    };
    by_store: ProfitStoreRow[];
  };
  sales: {
    totals: {
      count: number;
      revenue: number;
      cost: number;
      profit: number;
    };
    by_store: ProfitStoreRow[];
  };
}

export interface ProfitReportParams {
  date_from?: string;
  date_to?: string;
  store_id?: number;
}

export async function fetchProfitReportApi(
  token: string,
  params: ProfitReportParams = {},
): Promise<ProfitReportResponse> {
  const qs = new URLSearchParams();
  if (params.date_from) qs.set('date_from', params.date_from);
  if (params.date_to) qs.set('date_to', params.date_to);
  if (params.store_id) qs.set('store_id', String(params.store_id));
  const query = qs.toString();

  return apiRequest<ProfitReportResponse>(`/reports/profit${query ? `?${query}` : ''}`, {
    token,
  });
}
