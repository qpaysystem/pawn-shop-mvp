import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, FlatList, Pressable, Text, TextInput, View } from 'react-native';
import { useAuth } from '@/src/auth/AuthContext';
import { PassportPhotoFill } from '@/src/components/PassportPhotoFill';
import {
  Card,
  FieldLabel,
  PrimaryButton,
  Screen,
  Subtitle,
  colors,
  fieldStyles,
  numericKeyboardProps,
} from '@/src/components/Screen';
import type { PassportParseResult } from '@/src/types/client';
import { searchClientsApi } from '@/src/api/clients';
import { usePledgeWizardStore } from '@/src/store/pledgeWizardStore';
import type { ClientSearchResult } from '@/src/types/client';
import { clientSearchMinLength } from '@/src/utils/clientSearch';

export default function CustomerScreen() {
  const router = useRouter();
  const { token } = useAuth();
  const selectedClient = usePledgeWizardStore((s) => s.selectedClient);
  const newClient = usePledgeWizardStore((s) => s.newClient);
  const setSelectedClient = usePledgeWizardStore((s) => s.setSelectedClient);
  const setNewClient = usePledgeWizardStore((s) => s.setNewClient);

  const [mode, setMode] = useState<'search' | 'create'>('search');
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<ClientSearchResult[]>([]);
  const [busy, setBusy] = useState(false);

  const [form, setForm] = useState({
    last_name: newClient?.last_name ?? '',
    first_name: newClient?.first_name ?? '',
    patronymic: newClient?.patronymic ?? '',
    phone: newClient?.phone ?? '',
    passport_data: newClient?.passport_data ?? '',
  });

  const onSearch = async () => {
    const trimmed = query.trim();
    const minLen = clientSearchMinLength(trimmed);
    if (!token || trimmed.length < minLen) {
      Alert.alert(
        'Поиск',
        minLen === 3
          ? 'Для телефона введите минимум 3 цифры'
          : 'Введите минимум 2 символа (ФИО или телефон)',
      );
      return;
    }
    setBusy(true);
    try {
      const data = await searchClientsApi(token, trimmed);
      setResults(data);
    } catch (e) {
      Alert.alert('Ошибка', e instanceof Error ? e.message : 'Поиск не удался');
    } finally {
      setBusy(false);
    }
  };

  const applyPassportParse = (parsed: PassportParseResult) => {
    setForm((f) => ({
      ...f,
      last_name: parsed.last_name ?? f.last_name,
      first_name: parsed.first_name ?? f.first_name,
      patronymic: parsed.patronymic ?? f.patronymic,
      passport_data: parsed.passport_data ?? f.passport_data,
    }));
  };

  const onCreate = () => {
    if (!form.last_name || !form.first_name || !form.phone) {
      Alert.alert('Клиент', 'Заполните фамилию, имя и телефон');
      return;
    }
    setNewClient(form);
    router.push('/(app)/new-pledge/item');
  };

  const onSelect = (c: ClientSearchResult) => {
    setSelectedClient(c);
    router.push('/(app)/new-pledge/item');
  };

  const onNext = () => {
    if (selectedClient) {
      router.push('/(app)/new-pledge/item');
      return;
    }
    Alert.alert('Клиент', 'Выберите клиента из поиска или создайте нового');
  };

  return (
    <Screen scroll={false}>
      <Subtitle>Шаг 2 из 6 — поиск или создание клиента</Subtitle>
      <View style={{ flexDirection: 'row', gap: 8, marginBottom: 12 }}>
        <TabChip label="Поиск" active={mode === 'search'} onPress={() => setMode('search')} />
        <TabChip label="Новый" active={mode === 'create'} onPress={() => setMode('create')} />
      </View>

      {mode === 'search' ? (
        <>
          <TextInput
            style={fieldStyles.input}
            placeholder="ФИО, телефон (7913…, 913…), паспорт…"
            value={query}
            onChangeText={setQuery}
            onSubmitEditing={onSearch}
            keyboardType="default"
            autoComplete="off"
            textContentType="none"
          />
          <PrimaryButton label={busy ? 'Поиск…' : 'Найти'} onPress={onSearch} disabled={busy} />
          <FlatList
            data={results}
            keyExtractor={(item) => String(item.id)}
            style={{ marginTop: 12, flex: 1 }}
            ListEmptyComponent={
              <Text style={{ color: colors.muted, marginTop: 8 }}>
                {selectedClient
                  ? `Выбран: ${selectedClient.full_name}`
                  : 'Результаты появятся после поиска'}
              </Text>
            }
            renderItem={({ item }) => (
              <Pressable onPress={() => onSelect(item)}>
                <Card
                  style={{
                    borderColor: selectedClient?.id === item.id ? colors.primary : undefined,
                    borderWidth: selectedClient?.id === item.id ? 2 : 1,
                  }}
                >
                  <Text style={{ fontWeight: '600' }}>{item.full_name}</Text>
                  <Text style={{ color: colors.muted }}>{item.phone}</Text>
                </Card>
              </Pressable>
            )}
          />
          <PrimaryButton label="Далее: предмет" onPress={onNext} />
        </>
      ) : (
        <>
          <PassportPhotoFill onParsed={applyPassportParse} />
          <FieldLabel>Фамилия *</FieldLabel>
          <TextInput
            style={fieldStyles.input}
            value={form.last_name}
            onChangeText={(v) => setForm((f) => ({ ...f, last_name: v }))}
          />
          <FieldLabel>Имя *</FieldLabel>
          <TextInput
            style={fieldStyles.input}
            value={form.first_name}
            onChangeText={(v) => setForm((f) => ({ ...f, first_name: v }))}
          />
          <FieldLabel>Отчество</FieldLabel>
          <TextInput
            style={fieldStyles.input}
            value={form.patronymic}
            onChangeText={(v) => setForm((f) => ({ ...f, patronymic: v }))}
          />
          <FieldLabel>Телефон *</FieldLabel>
          <TextInput
            style={fieldStyles.input}
            keyboardType="phone-pad"
            {...numericKeyboardProps}
            value={form.phone}
            onChangeText={(v) => setForm((f) => ({ ...f, phone: v }))}
          />
          <FieldLabel>Паспорт</FieldLabel>
          <TextInput
            style={fieldStyles.input}
            value={form.passport_data}
            onChangeText={(v) => setForm((f) => ({ ...f, passport_data: v }))}
          />
          <PrimaryButton label="Сохранить и продолжить" onPress={onCreate} />
        </>
      )}
    </Screen>
  );
}

function TabChip({
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
      style={{
        flex: 1,
        paddingVertical: 10,
        borderRadius: 8,
        backgroundColor: active ? colors.primary : '#fff',
        borderWidth: 1,
        borderColor: colors.border,
        alignItems: 'center',
      }}
    >
      <Text style={{ color: active ? '#fff' : colors.text, fontWeight: '600' }}>{label}</Text>
    </Pressable>
  );
}
