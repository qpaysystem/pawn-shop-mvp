import { useCallback, useEffect, useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, Text, View } from 'react-native';
import { useAuth } from '@/src/auth/AuthContext';
import { fetchProfitReportApi, type ProfitReportResponse } from '@/src/api/reports';
import { Card, Screen, Title, colors } from '@/src/components/Screen';
import { formatApiErrorMessage } from '@/src/utils/formatApiError';
import { formatMoney } from '@/src/utils/loan';

type PeriodKey = 'month' | 'quarter' | 'year';

function periodRange(key: PeriodKey): { from: string; to: string } {
  const to = new Date();
  const from = new Date();
  if (key === 'month') {
    from.setDate(1);
  } else if (key === 'quarter') {
    from.setMonth(from.getMonth() - 3);
    from.setDate(1);
  } else {
    from.setMonth(0);
    from.setDate(1);
  }
  const fmt = (d: Date) => d.toISOString().slice(0, 10);

  return { from: fmt(from), to: fmt(to) };
}

const periods: { key: PeriodKey; label: string }[] = [
  { key: 'month', label: 'Месяц' },
  { key: 'quarter', label: '3 мес.' },
  { key: 'year', label: 'Год' },
];

export default function ReportsScreen() {
  const { token, user, catalogs } = useAuth();
  const [period, setPeriod] = useState<PeriodKey>('quarter');
  const [storeId, setStoreId] = useState<number | undefined>(undefined);
  const [data, setData] = useState<ProfitReportResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const stores = catalogs?.stores ?? [];
  const canPickStore = stores.length > 1 && user?.role === 'super-admin';
  const canView = user?.role === 'super-admin' || user?.role === 'manager';

  const range = useMemo(() => periodRange(period), [period]);

  const load = useCallback(async () => {
    if (!token || !canView) return;
    setLoading(true);
    setError(null);
    try {
      const res = await fetchProfitReportApi(token, {
        date_from: range.from,
        date_to: range.to,
        store_id: storeId,
      });
      setData(res);
    } catch (e) {
      setData(null);
      setError(formatApiErrorMessage(e));
    } finally {
      setLoading(false);
    }
  }, [token, canView, range.from, range.to, storeId]);

  useEffect(() => {
    load();
  }, [load]);

  if (!canView) {
    return (
      <Screen>
        <Title>Отчёты</Title>
        <Text style={{ color: colors.muted }}>
          Доступно ролям менеджер и администратор.
        </Text>
      </Screen>
    );
  }

  return (
    <Screen scroll={false} padded={false} loading={loading && !data}>
      <ScrollView
        style={{ flex: 1 }}
        contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={load} />}
      >
        <Text style={{ color: colors.muted, marginBottom: 12 }}>
          Период: {data?.date_from ?? range.from} — {data?.date_to ?? range.to}
        </Text>

        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 12 }}>
          {periods.map((p) => (
            <Pressable
              key={p.key}
              onPress={() => setPeriod(p.key)}
              style={{
                paddingHorizontal: 14,
                paddingVertical: 8,
                borderRadius: 20,
                backgroundColor: period === p.key ? colors.primary : '#fff',
                borderWidth: 1,
                borderColor: colors.border,
              }}
            >
              <Text style={{ color: period === p.key ? '#fff' : colors.text }}>{p.label}</Text>
            </Pressable>
          ))}
        </View>

        {canPickStore ? (
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 16 }}>
            <Pressable
              onPress={() => setStoreId(undefined)}
              style={{
                paddingHorizontal: 12,
                paddingVertical: 8,
                borderRadius: 20,
                backgroundColor: storeId === undefined ? colors.primary : '#fff',
                borderWidth: 1,
                borderColor: colors.border,
              }}
            >
              <Text style={{ color: storeId === undefined ? '#fff' : colors.text }}>Все точки</Text>
            </Pressable>
            {stores.map((s) => (
              <Pressable
                key={s.id}
                onPress={() => setStoreId(s.id)}
                style={{
                  paddingHorizontal: 12,
                  paddingVertical: 8,
                  borderRadius: 20,
                  backgroundColor: storeId === s.id ? colors.primary : '#fff',
                  borderWidth: 1,
                  borderColor: colors.border,
                }}
              >
                <Text style={{ color: storeId === s.id ? '#fff' : colors.text }} numberOfLines={1}>
                  {s.name}
                </Text>
              </Pressable>
            ))}
          </View>
        ) : null}

        {error ? <Text style={{ color: colors.danger, marginBottom: 12 }}>{error}</Text> : null}

        {data ? (
          <>
            <Text style={{ fontSize: 18, fontWeight: '700', color: colors.primary, marginBottom: 8 }}>
              Прибыль с залогов
            </Text>
            <Card>
              <StatRow label="Выкупов" value={String(data.pawn.totals.count)} />
              <StatRow label="Сумма займов" value={formatMoney(data.pawn.totals.loan_amount)} />
              <StatRow label="Сумма выкупов" value={formatMoney(data.pawn.totals.buyback_amount)} />
              <StatRow
                label="Прибыль (проценты)"
                value={formatMoney(data.pawn.totals.profit)}
                highlight
              />
            </Card>

            {data.pawn.by_store.filter((r) => r.count > 0).length > 1 ? (
              <StoreTable
                rows={data.pawn.by_store.filter((r) => r.count > 0)}
                profitLabel="Прибыль"
              />
            ) : null}

            <Text
              style={{
                fontSize: 18,
                fontWeight: '700',
                color: colors.primary,
                marginTop: 20,
                marginBottom: 8,
              }}
            >
              Прибыль по продажам
            </Text>
            <Card>
              <StatRow label="Продаж" value={String(data.sales.totals.count)} />
              <StatRow label="Выручка" value={formatMoney(data.sales.totals.revenue)} />
              <StatRow label="Себестоимость" value={formatMoney(data.sales.totals.cost)} />
              <StatRow label="Прибыль" value={formatMoney(data.sales.totals.profit)} highlight />
            </Card>

            {data.sales.by_store.filter((r) => r.count > 0).length > 1 ? (
              <StoreTable
                rows={data.sales.by_store.filter((r) => r.count > 0)}
                profitLabel="Прибыль"
              />
            ) : null}

            <Card style={{ marginTop: 16, backgroundColor: '#f0fdf4' }}>
              <Text style={{ fontWeight: '700', fontSize: 16 }}>Итого прибыль</Text>
              <Text style={{ fontSize: 24, fontWeight: '700', color: '#166534', marginTop: 8 }}>
                {formatMoney(data.pawn.totals.profit + data.sales.totals.profit)}
              </Text>
            </Card>
          </>
        ) : null}
      </ScrollView>
    </Screen>
  );
}

function StatRow({
  label,
  value,
  highlight,
}: {
  label: string;
  value: string;
  highlight?: boolean;
}) {
  return (
    <View
      style={{
        flexDirection: 'row',
        justifyContent: 'space-between',
        marginBottom: 8,
        gap: 12,
      }}
    >
      <Text style={{ color: colors.muted, flex: 1 }}>{label}</Text>
      <Text style={{ fontWeight: highlight ? '700' : '600', color: highlight ? '#166534' : colors.text }}>
        {value}
      </Text>
    </View>
  );
}

function StoreTable({
  rows,
  profitLabel,
}: {
  rows: { store_name: string; count: number; profit: number }[];
  profitLabel: string;
}) {
  return (
    <View style={{ marginTop: 8, marginBottom: 8 }}>
      <Text style={{ fontWeight: '600', marginBottom: 8, color: colors.muted }}>По точкам</Text>
      {rows.map((row) => (
        <View
          key={row.store_name}
          style={{
            flexDirection: 'row',
            justifyContent: 'space-between',
            paddingVertical: 8,
            borderBottomWidth: 1,
            borderBottomColor: colors.border,
          }}
        >
          <View style={{ flex: 1, paddingRight: 8 }}>
            <Text style={{ fontWeight: '500' }}>{row.store_name}</Text>
            <Text style={{ color: colors.muted, fontSize: 12 }}>{row.count} операций</Text>
          </View>
          <Text style={{ fontWeight: '600', color: '#166534' }}>
            {formatMoney(row.profit)}
          </Text>
        </View>
      ))}
    </View>
  );
}
