import { useRouter } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { useAuth } from '@/src/auth/AuthContext';
import { listAllPawnContractsApi } from '@/src/api/pawnContracts';
import { Card, PrimaryButton, Screen, Subtitle, Title, colors } from '@/src/components/Screen';
import type { PawnContract } from '@/src/types/pawn';
import { formatMoney } from '@/src/utils/loan';
import { usePledgeWizardStore } from '@/src/store/pledgeWizardStore';

export default function DashboardScreen() {
  const { user, token, catalogs } = useAuth();
  const storeLabel =
    user?.store_name ??
    catalogs?.stores?.find((s) => s.id === user?.store_id)?.name ??
    catalogs?.stores?.[0]?.name ??
    'Точка не назначена';
  const router = useRouter();
  const resetWizard = usePledgeWizardStore((s) => s.reset);
  const [stats, setStats] = useState({ active: 0, overdue: 0 });
  const [recent, setRecent] = useState<PawnContract[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    try {
      const storeFilter = user?.role === 'super-admin' ? undefined : user?.store_id ?? undefined;
      const [activeRes, overdueRes] = await Promise.all([
        listAllPawnContractsApi(token, { status: 'active', store_id: storeFilter }),
        listAllPawnContractsApi(token, { status: 'overdue', store_id: storeFilter }),
      ]);
      setStats({
        active: activeRes.meta?.total ?? activeRes.data.length,
        overdue: overdueRes.meta?.total ?? overdueRes.data.length,
      });
      setRecent(activeRes.data.slice(0, 5));
    } finally {
      setLoading(false);
    }
  }, [token, user?.store_id, user?.role]);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <Screen loading={loading}>
      <Title>{`Здравствуйте, ${user?.name?.split(' ')[0] ?? 'сотрудник'}`}</Title>
      <Subtitle>{storeLabel}</Subtitle>

      <View style={{ flexDirection: 'row', gap: 12, marginBottom: 16 }}>
        <Card style={{ flex: 1 }}>
          <Text style={{ fontSize: 28, fontWeight: '700', color: colors.primary }}>
            {stats.active}
          </Text>
          <Text style={{ color: colors.muted }}>Активные</Text>
        </Card>
        <Card style={{ flex: 1 }}>
          <Text style={{ fontSize: 28, fontWeight: '700', color: colors.danger }}>
            {stats.overdue}
          </Text>
          <Text style={{ color: colors.muted }}>Просрочка</Text>
        </Card>
      </View>

      <PrimaryButton
        label="Новый приём залога"
        onPress={() => {
          resetWizard();
          router.push('/(app)/(tabs)/new-pledge');
        }}
      />
      <Pressable
        onPress={() => router.push('/(app)/(tabs)/clients')}
        style={{
          marginTop: 12,
          paddingVertical: 14,
          borderRadius: 10,
          alignItems: 'center',
          borderWidth: 1,
          borderColor: colors.primary,
        }}
      >
        <Text style={{ color: colors.primary, fontSize: 16, fontWeight: '600' }}>
          Клиенты базы
        </Text>
      </Pressable>

      <Text style={{ marginTop: 24, marginBottom: 8, fontWeight: '600', fontSize: 16 }}>
        Недавние залоги
      </Text>
      {recent.length === 0 ? (
        <Text style={{ color: colors.muted }}>Нет активных договоров</Text>
      ) : (
        recent.map((p) => (
          <Pressable key={p.id} onPress={() => router.push(`/(app)/pledge/${p.id}`)}>
            <Card>
              <Text style={{ fontWeight: '600' }}>{p.contract_number}</Text>
              <Text style={{ color: colors.muted }}>
                {p.client?.full_name ?? `Клиент #${p.client_id}`}
              </Text>
              <Text>{formatMoney(p.loan_amount)}</Text>
            </Card>
          </Pressable>
        ))
      )}
    </Screen>
  );
}
