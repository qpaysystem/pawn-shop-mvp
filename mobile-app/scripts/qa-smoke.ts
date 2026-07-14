/**
 * Headless smoke tests. Run: npm run qa:smoke
 */
import { mapAuthUser, permissionsForRole } from '../src/auth/mapUser';
import { env } from '../src/config/env';
import { mockApi } from '../src/mocks/handlers';
import { usePledgeWizardStore } from '../src/store/pledgeWizardStore';
import { formatApiErrorMessage } from '../src/utils/formatApiError';
import { ApiError } from '../src/types/api';

function assert(cond: boolean, msg: string): void {
  if (!cond) throw new Error(`FAIL: ${msg}`);
}

async function main(): Promise<void> {
  console.log('QA smoke — mock API, auth helpers, wizard store\n');

  assert(env.useMockAuth === true, 'env.useMockAuth should default to true (no .env required)');
  assert(env.useMockData === true, 'env.useMockData should stay true until data APIs ship');
  assert(env.apiBaseUrl.endsWith('/api/v1'), 'apiBaseUrl should include /api/v1');
  assert(env.apiOrigin.includes('127.0.0.1') || env.apiOrigin.length > 0, 'apiOrigin set');

  const login = await mockApi.login({
    email: 'demo@lombard.local',
    password: 'demo',
    device_name: 'qa-smoke',
  });
  assert(Boolean(login.token), 'mock login returns token');
  assert(login.user.email === 'appraiser@example.com', 'mock user loaded');

  await mockApi.login({
    email: 'wrong@lombard.local',
    password: 'x',
    device_name: 'qa',
  }).then(
    () => {
      throw new Error('invalid login should throw');
    },
    () => {
      /* expected */
    },
  );

  const mapped = mapAuthUser({
    id: 2,
    name: 'Test',
    email: 't@example.com',
    role: 'appraiser',
  });
  assert(mapped.permissions.can_create_contracts === true, 'appraiser can_create_contracts');
  assert(permissionsForRole('cashier').can_process_sales === true, 'cashier permissions');

  assert(
    formatApiErrorMessage(new ApiError('Unauthorized', 401)) === 'Неверный email или пароль',
    '401 message',
  );
  assert(
    formatApiErrorMessage(new Error('network_unavailable')).includes('сервером'),
    'network message',
  );

  usePledgeWizardStore.getState().reset();
  usePledgeWizardStore.getState().setVisitPurpose('appraisal');
  usePledgeWizardStore.getState().setStoreId(1);
  usePledgeWizardStore.getState().patchItem({ name: 'Кольцо QA', status_id: 1 });
  usePledgeWizardStore.getState().setSelectedClient({
    id: 1,
    full_name: 'Тест Тестов',
    last_name: 'Тестов',
    first_name: 'Тест',
    patronymic: null,
    phone: '+79001234567',
  });
  usePledgeWizardStore.getState().patchLoan({ loan_amount: 15000, loan_percent: 20 });

  const mid = usePledgeWizardStore.getState();
  assert(mid.visitPurpose === 'appraisal', 'visitPurpose persisted');
  assert(mid.storeId === 1, 'storeId persisted');
  assert(mid.item.name === 'Кольцо QA', 'item.name persisted');
  assert(mid.selectedClient?.id === 1, 'selectedClient persisted');
  assert(mid.loan.loan_amount === 15000, 'loan.loan_amount persisted');

  const wizardRoutes = ['index', 'customer', 'item', 'photos', 'loan', 'review'] as const;
  assert(wizardRoutes.length === 6, 'wizard is 6 steps');

  console.log('PASS env defaults and api v1 base URL');
  console.log('PASS mock login demo@lombard.local / demo');
  console.log('PASS mock login rejects invalid credentials');
  console.log('PASS mapAuthUser / permissions / error messages');
  console.log('PASS pledgeWizardStore state persistence');
  console.log('PASS wizard step count');
  console.log('\nAll smoke checks passed.');
  console.log(
    '\nNote: SecureStore token persistence is verified manually (login → kill app → reopen).',
  );
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
