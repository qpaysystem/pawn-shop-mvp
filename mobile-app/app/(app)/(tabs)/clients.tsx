import { useRouter } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useAuth } from '@/src/auth/AuthContext';
import { listClientsApi } from '@/src/api/clients';
import { Card, colors, fieldStyles } from '@/src/components/Screen';
import type { Client } from '@/src/types/client';

export default function ClientsScreen() {
  const { token } = useAuth();
  const router = useRouter();
  const [q, setQ] = useState('');
  const [items, setItems] = useState<Client[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  const loadPage = useCallback(
    async (pageNum: number, replace: boolean) => {
      if (!token) return;
      if (pageNum === 1) setLoading(true);
      else setLoadingMore(true);
      try {
        const res = await listClientsApi(token, {
          q: q.trim() || undefined,
          page: pageNum,
        });
        setItems((prev) => (replace ? res.data : [...prev, ...res.data]));
        setPage(res.meta?.current_page ?? pageNum);
        setLastPage(res.meta?.last_page ?? 1);
        setTotal(res.meta?.total ?? res.data.length);
      } finally {
        setLoading(false);
        setLoadingMore(false);
      }
    },
    [token, q],
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

  const listHeader = useMemo(
    () => (
      <View style={styles.header}>
        <TextInput
          style={fieldStyles.input}
          placeholder="Поиск по ФИО, телефону, email…"
          value={q}
          onChangeText={setQ}
          onSubmitEditing={reload}
          returnKeyType="search"
          clearButtonMode="while-editing"
        />
        <Text style={styles.count}>
          {loading ? 'Загрузка…' : `Всего в базе: ${total}`}
        </Text>
      </View>
    ),
    [q, reload, loading, total],
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
            <Text style={styles.empty}>Клиенты не найдены</Text>
          ) : null
        }
        ListFooterComponent={
          loadingMore ? (
            <ActivityIndicator color={colors.primary} style={styles.footerLoader} />
          ) : null
        }
        renderItem={({ item }) => (
          <Pressable onPress={() => router.push(`/(app)/client/${item.id}`)}>
            <Card>
              <View style={styles.rowBetween}>
                <Text style={styles.name}>{item.full_name}</Text>
                {item.blacklist_flag ? <BlacklistBadge /> : null}
              </View>
              <Text style={styles.muted}>{item.phone}</Text>
              {item.email ? <Text style={styles.email}>{item.email}</Text> : null}
              {item.client_type === 'legal' ? (
                <Text style={styles.legal}>Юр. лицо</Text>
              ) : null}
            </Card>
          </Pressable>
        )}
      />
    </SafeAreaView>
  );
}

function BlacklistBadge() {
  return (
    <View style={styles.blacklist}>
      <Text style={styles.blacklistText}>ЧС</Text>
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
  count: {
    color: colors.muted,
    fontSize: 13,
    marginBottom: 8,
  },
  empty: {
    color: colors.muted,
    textAlign: 'center',
    marginTop: 24,
  },
  footerLoader: {
    marginVertical: 16,
  },
  rowBetween: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 8,
  },
  name: {
    fontWeight: '600',
    flex: 1,
  },
  muted: {
    color: colors.muted,
    marginTop: 4,
  },
  email: {
    color: colors.muted,
    marginTop: 2,
    fontSize: 13,
  },
  legal: {
    color: colors.muted,
    marginTop: 2,
    fontSize: 12,
  },
  blacklist: {
    backgroundColor: '#fee2e2',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
  },
  blacklistText: {
    fontSize: 11,
    color: '#991b1b',
    fontWeight: '600',
  },
});
