import { create } from 'zustand';
import type { ClientSearchResult } from '@/src/types/client';
import type { ItemPayload, LoanTermsPayload, VisitPurpose } from '@/src/types/pawn';

export interface WizardPhoto {
  uri: string;
  id: string;
}

interface PledgeWizardState {
  visitPurpose: VisitPurpose;
  storeId: number | null;
  selectedClient: ClientSearchResult | null;
  newClient: {
    last_name: string;
    first_name: string;
    patronymic: string;
    phone: string;
    passport_data: string;
  } | null;
  item: Partial<ItemPayload> & { name: string };
  photos: WizardPhoto[];
  loan: Partial<LoanTermsPayload>;
  reset: () => void;
  setVisitPurpose: (v: VisitPurpose) => void;
  setStoreId: (id: number) => void;
  setSelectedClient: (c: ClientSearchResult | null) => void;
  setNewClient: (c: PledgeWizardState['newClient']) => void;
  patchItem: (patch: Partial<ItemPayload>) => void;
  setPhotos: (photos: WizardPhoto[]) => void;
  addPhoto: (photo: WizardPhoto) => void;
  removePhoto: (id: string) => void;
  patchLoan: (patch: Partial<LoanTermsPayload>) => void;
}

const defaultLoan = (): Partial<LoanTermsPayload> => ({
  loan_date: new Date().toISOString().slice(0, 10),
  expiry_date: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
  loan_percent: 20,
});

const initialState = {
  visitPurpose: 'appraisal' as VisitPurpose,
  storeId: null as number | null,
  selectedClient: null as ClientSearchResult | null,
  newClient: null as PledgeWizardState['newClient'],
  item: {
    name: '',
    status_id: 1,
  },
  photos: [] as WizardPhoto[],
  loan: defaultLoan(),
};

export const usePledgeWizardStore = create<PledgeWizardState>((set) => ({
  ...initialState,
  reset: () => set({ ...initialState, loan: defaultLoan() }),
  setVisitPurpose: (visitPurpose) => set({ visitPurpose }),
  setStoreId: (storeId) => set({ storeId }),
  setSelectedClient: (selectedClient) => set({ selectedClient, newClient: null }),
  setNewClient: (newClient) => set({ newClient, selectedClient: null }),
  patchItem: (patch) => set((s) => ({ item: { ...s.item, ...patch } })),
  setPhotos: (photos) => set({ photos }),
  addPhoto: (photo) => set((s) => ({ photos: [...s.photos, photo] })),
  removePhoto: (id) =>
    set((s) => ({ photos: s.photos.filter((p) => p.id !== id) })),
  patchLoan: (patch) => set((s) => ({ loan: { ...s.loan, ...patch } })),
}));
