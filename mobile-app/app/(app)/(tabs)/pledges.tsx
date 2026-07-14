import { useRouter } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useAuth } from '@/src/auth/AuthContext';
import {
  listPawnContractsApi,
  payInterestPawnContractApi,
  redeemPawnContractApi,
} from '@/src/api/pawnContracts';
import { Card, colors, fieldStyles } from '@/src/components/Screen';
import type { PawnComputedStatus, PawnContract } from '@/src/types/pawn';
import { formatApiErrorMessage } from '@/src/utils/formatApiError';
import { calculateInterestAmount, formatMoney } from '@/src/utils/loan';

const filters: { key: PawnComputedStatus | 'all'; label: string }[] = [
  { key: 'all', label: 'Все' },
  { key: 'active', label: 'Активные' },
  { key: 'overdue', label: 'Просрочка' },
  { key: 'redeemed', label: 'Выкуплены' },
];

const PAGE_SIZE = 50;

export default function ActivePledgesScreen() {
  const { token, user, catalogs } = useAuth();
  const router = useRouter();
  const [status, setStatus] = useState<PawnComputedStatus | 'all'>('active');
  const [storeId, setStoreId] = useState<number | undefined>(user?.store_id ?? undefined);
  const [q, setQ] = useState('');
  const [items, setItems] = useState<PawnContract[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  const stores = catalogs?.stores ?? [];
  const canPickStore = stores.length > 1 && user?.role === 'super-admin';

  useEffect(() => {
    if (user?.store_id && !canPickStore) {
      setStoreId(user.store_id);
    }
  }, [user?.store_id, canPickStore]);

  const loadPage = useCallback(
    async (pageNum: number, replace: boolean) => {
      if (!token) return;
      if (pageNum === 1) setLoading(true);
      else setLoadingMore(true);
      setError(null);
      try {
        const res = await listPawnContractsApi(token, {
          status,
          q: q.trim() || undefined,
          store_id: storeId,
          page: pageNum,
          per_page: PAGE_SIZE,
        });
        setItems((prev) => (replace ? res.data : [...prev, ...res.data]));
        setPage(res.meta?.current_page ?? pageNum);
        setLastPage(res.meta?.last_page ?? 1);
        setTotal(res.meta?.total ?? res.data.length);
      } catch (e) {
        if (replace) {
          setItems([]);
          setTotal(0);
        }
        setError(formatApiErrorMessage(e));
      } finally {
        setLoading(false);
        setLoadingMore(false);
      }
    },
    [token, status, q, storeId],
  );

  const reload = useCallback(() => {
    loadPage(1, true);
  }, [loadPage]);

  useEffect(() => {
    reload();
  }, [reload]);

  const loadMore = () => {
    if (loadingMore || loading || page >= lastPage) return;
    loadPage(page + 1, false);
  };

  const buybackAmount = (item: PawnContract) =>
    item.buyback_amount ?? item.redemption_amount ?? item.loan_amount;

  const interestAmount = (item: PawnContract) =>
    calculateInterestAmount(item.loan_amount, item.loan_percent, buybackAmount(item));

  const openPledgeActions = (item: PawnContract) => {
    if (item.is_redeemed || item.computed_status === 'redeemed') {
      Alert.alert('Договор', 'Залог уже выкуплен.');
      return;
    }
    if (!user?.permissions.can_process_sales) {
      Alert.alert(
        'Нет прав',
        'Выкуп и оплата процентов доступны роли кассир или менеджер.',
      );
      return;
    }

    const buyback = buybackAmount(item);
    const interest = interestAmount(item);

    Alert.alert(
      item.contract_number,
      `${item.client?.full_name ?? `Клиент #${item.client_id}`}\n\nВыкуп: ${formatMoney(buyback)}\nПроценты: ${formatMoney(interest)}`,
      [
        {
          text: 'Выкупить',
          onPress: () => {
            Alert.alert(
              'Подтвердите выкуп',
              `Списать ${formatMoney(buyback)} и закрыть договор?`,
              [
                { text: 'Отмена', style: 'cancel' },
                { text: 'Выкупить', style: 'destructive', onPress: () => runRedeem(item) },
              ],
            );
          },
        },
        {
          text: 'Оплатить проценты',
          onPress: () => {
            Alert.alert(
              'Оплата процентов',
              `Принять ${formatMoney(interest)} и продлить срок на 30 дней?`,
              [
                { text: 'Отмена', style: 'cancel' },
                { text: 'Оплатить', onPress: () => runPayInterest(item) },
              ],
            );
          },
        },
        { text: 'Отмена', style: 'cancel' },
      ],
    );
  };

  const runRedeem = async (item: PawnContract) => {
    if (!token) return;
    setBusyId(item.id);
    try {
      await redeemPawnContractApi(token, item.id);
      Alert.alert('Готово', `Договор ${item.contract_number} выкуплен.`);
      reload();
    } catch (e) {
      Alert.alert('Ошибка', formatApiErrorMessage(e));
    } finally {
      setBusyId(null);
    }
  };

  const runPayInterest = async (item: PawnContract) => {
    if (!token) return;
    setBusyId(item.id);
    try {
      const updated = await payInterestPawnContractApi(token, item.id, 30);
      Alert.alert(
        'Готово',
        `Проценты приняты. Новый срок до ${updated.expiry_date ?? '—'}.`,
      );
      reload();
    } catch (e) {
      Alert.alert('Ошибка', formatApiErrorMessage(e));
    } finally {
      setBusyId(null);
    }
  };

  const listHeader = useMemo(
    () => (
      <PledgesListHeader
        q={q}
        onChangeQ={setQ}
        onSearch={reload}
        status={status}
        onChangeStatus={setStatus}
        storeId={storeId}
        onChangeStoreId={setStoreId}
        stores={stores}
        canPickStore={canPickStore}
        userStoreName={user?.store_name}
        total={total}
        loading={loading}
        error={error}
      />
    ),
    [
      q,
      reload,
      status,
      storeId,
      stores,
      canPickStore,
      user?.store_name,
      total,
      loading,
      error,
    ],
  );

  return (
    <SafeAreaView style={styles.root} edges={['left', 'right']}>
      <FlatList
        data={items}
        keyExtractor={(item) => String(item.id)}
        style={styles.list}
        contentContainerStyle={styles.listContent}
        keyboardShouldPersistTaps="handled"
        refreshing={loading}
        onRefresh={reload}
        onEndReached={loadMore}
        onEndReachedThreshold={0.35}
        ListHeaderComponent={listHeader}
        ListEmptyComponent={
          !loading ? (
            <Text style={styles.empty}>
              {error ? 'Не удалось загрузить список' : 'Договоры не найдены'}
            </Text>
          ) : null
        }
        ListFooterComponent={
          loadingMore ? (
            <ActivityIndicator color={colors.primary} style={styles.footerLoader} />
          ) : null
        }
        renderItem={({ item }) => (
          <Pressable
            onPress={() => router.push(`/(app)/pledge/${item.id}`)}
            onLongPress={() => openPledgeActions(item)}
            delayLongPress={450}
            disabled={busyId === item.id}
            style={{ opacity: busyId === item.id ? 0.6 : 1 }}
          >
            <Card>
              <View style={styles.rowBetween}>
                <Text style={styles.contractNumber}>{item.contract_number}</Text>
                <StatusBadge status={item.computed_status} />
              </View>
              <Text style={styles.muted}>
                {item.client?.full_name ?? `Клиент #${item.client_id}`}
              </Text>
              <Text style={styles.itemName}>{item.item?.name ?? 'Предмет'}</Text>
              <Text style={styles.amount}>{formatMoney(item.loan_amount)}</Text>
              {!item.is_redeemed ? (
                <Text style={styles.hint}>
                  Выкуп {formatMoney(buybackAmount(item))} · проценты{' '}
                  {formatMoney(interestAmount(item))}
                </Text>
              ) : null}
            </Card>
          </Pressable>
        )}
      />
    </SafeAreaView>
  );
}

function PledgesListHeader({
  q,
  onChangeQ,
  onSearch,
  status,
  onChangeStatus,
  storeId,
  onChangeStoreId,
  stores,
  canPickStore,
  userStoreName,
  total,
  loading,
  error,
}: {
  q: string;
  onChangeQ: (v: string) => void;
  onSearch: () => void;
  status: PawnComputedStatus | 'all';
  onChangeStatus: (v: PawnComputedStatus | 'all') => void;
  storeId: number | undefined;
  onChangeStoreId: (v: number | undefined) => void;
  stores: { id: number; name: string }[];
  canPickStore: boolean;
  userStoreName?: string | null;
  total: number;
  loading: boolean;
  error: string | null;
}) {
  return (
    <View style={styles.header}>
      <TextInput
        style={fieldStyles.input}
        placeholder="Поиск по ФИО, телефону, номеру…"
        value={q}
        onChangeText={onChangeQ}
        onSubmitEditing={onSearch}
        returnKeyType="search"
      />
      <Text style={styles.hintTop}>Долгое нажатие на залог — выкуп или оплата процентов</Text>
      <Text style={styles.countLine}>
        {loading ? 'Загрузка…' : `Найдено: ${total}`}
        {userStoreName && !canPickStore ? ` · ${userStoreName}` : ''}
      </Text>
      {canPickStore ? (
        <View style={styles.chips}>
          <Chip
            label="Все точки"
            active={storeId === undefined}
            onPress={() => onChangeStoreId(undefined)}
          />
          {stores.map((s) => (
            <Chip
              key={s.id}
              label={s.name}
              active={storeId === s.id}
              onPress={() => onChangeStoreId(s.id)}
            />
          ))}
        </View>
      ) : null}
      {error ? <Text style={styles.error}>{error}</Text> : null}
      <View style={styles.chips}>
        {filters.map((f) => (
          <Chip
            key={f.key}
            label={f.label}
            active={status === f.key}
            onPress={() => onChangeStatus(f.key)}
          />
        ))}
      </View>
    </View>
  );
}

function Chip({
  label,
  active,
  onPress,
}: {
  label: string;
  active: boolean;
  onPress: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      style={[styles.chip, active && styles.chipActive]}
    >
      <Text style={[styles.chipText, active && styles.chipTextActive]} numberOfLines={2}>
        {label}
      </Text>
    </Pressable>
  );
}

function StatusBadge({ status }: { status: PawnComputedStatus }) {
  const map = {
    active: { label: 'Активен', bg: '#d1fae5', fg: '#065f46' },
    overdue: { label: 'Просрочен', bg: '#fee2e2', fg: '#991b1b' },
    redeemed: { label: 'Выкуплен', bg: '#e5e7eb', fg: '#374151' },
  };
  const s = map[status];
  return (
    <View style={[styles.badge, { backgroundColor: s.bg }]}>
      <Text style={[styles.badgeText, { color: s.fg }]}>{s.label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: colors.bg,
  },
  list: {
    flex: 1,
  },
  listContent: {
    paddingHorizontal: 16,
    paddingBottom: 24,
  },
  header: {
    paddingTop: 8,
    paddingBottom: 4,
  },
  hintTop: {
    color: colors.muted,
    fontSize: 12,
    marginBottom: 4,
  },
  countLine: {
    color: colors.muted,
    fontSize: 13,
    marginBottom: 10,
  },
  chips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginBottom: 12,
  },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: colors.border,
    maxWidth: '100%',
  },
  chipActive: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  chipText: {
    color: colors.text,
    fontSize: 14,
  },
  chipTextActive: {
    color: '#fff',
  },
  error: {
    color: colors.danger,
    marginBottom: 8,
  },
  empty: {
    color: colors.muted,
    textAlign: 'center',
    marginTop: 24,
    marginBottom: 24,
  },
  footerLoader: {
    marginVertical: 16,
  },
  rowBetween: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 8,
  },
  contractNumber: {
    fontWeight: '600',
    flex: 1,
  },
  muted: {
    color: colors.muted,
    marginTop: 4,
  },
  itemName: {
    marginTop: 4,
  },
  amount: {
    fontWeight: '600',
    marginTop: 4,
  },
  hint: {
    color: colors.muted,
    fontSize: 12,
    marginTop: 4,
  },
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
  },
  badgeText: {
    fontSize: 12,
    fontWeight: '600',
  },
});
