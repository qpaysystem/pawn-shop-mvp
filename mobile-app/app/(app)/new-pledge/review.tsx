import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, Text, View } from 'react-native';
import { useAuth } from '@/src/auth/AuthContext';
import { createPawnContractApi } from '@/src/api/pawnContracts';
import { Card, PrimaryButton, Screen, Subtitle, Title, colors } from '@/src/components/Screen';
import { usePledgeWizardStore } from '@/src/store/pledgeWizardStore';
import { formatApiErrorMessage } from '@/src/utils/formatApiError';
import { calculateBuybackAmount, formatMoney } from '@/src/utils/loan';

export default function ReviewScreen() {
  const router = useRouter();
  const { token } = useAuth();
  const [busy, setBusy] = useState(false);
  const reset = usePledgeWizardStore((s) => s.reset);
  const state = usePledgeWizardStore();

  const clientLabel = state.selectedClient
    ? state.selectedClient.full_name
    : state.newClient
      ? `${state.newClient.last_name} ${state.newClient.first_name}`
      : '—';

  const loanAmount = state.loan.loan_amount ?? 0;
  const loanPercent = state.loan.loan_percent ?? 20;
  const buyback = calculateBuybackAmount(loanAmount, loanPercent);

  const onSubmit = async () => {
    if (!token || !state.storeId) {
      Alert.alert('Ошибка', 'Не выбрана точка');
      return;
    }
    if (!state.selectedClient && !state.newClient) {
      Alert.alert('Ошибка', 'Не выбран клиент');
      return;
    }
    if (!state.item.name) {
      Alert.alert('Ошибка', 'Укажите название предмета');
      return;
    }

    const payload: CreatePawnContractPayload = {
      store_id: state.storeId,
      visit_purpose: state.visitPurpose,
      item: {
        name: state.item.name,
        description: state.item.description ?? null,
        category_id: state.item.category_id ?? null,
        brand_id: state.item.brand_id ?? null,
        status_id: state.item.status_id ?? 1,
        initial_price: state.item.initial_price ?? null,
        current_price: state.item.initial_price ?? null,
      },
      loan: {
        loan_amount: loanAmount,
        loan_percent: loanPercent,
        loan_date: state.loan.loan_date ?? new Date().toISOString().slice(0, 10),
        expiry_date:
          state.loan.expiry_date ??
          new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
      },
    };

    if (state.selectedClient) {
      payload.client_id = state.selectedClient.id;
    } else if (state.newClient) {
      payload.client = state.newClient;
    }

    setBusy(true);
    try {
      const created = await createPawnContractApi(
        token,
        payload,
        state.photos.map((p) => p.uri),
      );
      reset();
      Alert.alert('Готово', `Договор ${created.contract_number} создан`, [
        {
          text: 'Открыть',
          onPress: () => router.replace(`/(app)/pledge/${created.id}`),
        },
        { text: 'На главную', onPress: () => router.replace('/(app)/(tabs)') },
      ]);
    } catch (e) {
      Alert.alert('Ошибка', formatApiErrorMessage(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen>
      <Title>Проверка</Title>
      <Subtitle>Шаг 6 из 6 — подтверждение перед отправкой</Subtitle>
      <Card>
        <Row k="Клиент" v={clientLabel} />
        <Row k="Предмет" v={state.item.name} />
        <Row k="Займ" v={formatMoney(loanAmount)} />
        <Row k="Процент" v={`${loanPercent}%`} />
        <Row k="Выкуп" v={formatMoney(buyback)} />
        <Row k="Фото" v={String(state.photos.length)} />
        <Row k="Цель визита" v={state.visitPurpose} />
      </Card>
      <Text style={{ fontSize: 12, color: colors.muted, marginBottom: 16 }}>
        При реальном API фото отправляются multipart; в mock режиме URI не сохраняются на сервере.
      </Text>
      <PrimaryButton
        label={busy ? 'Отправка…' : 'Создать договор'}
        onPress={onSubmit}
        disabled={busy}
      />
    </Screen>
  );
}

function Row({ k, v }: { k: string; v: string }) {
  return (
    <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8 }}>
      <Text style={{ color: colors.muted }}>{k}</Text>
      <Text style={{ fontWeight: '600', maxWidth: '60%', textAlign: 'right' }}>{v}</Text>
    </View>
  );
}
