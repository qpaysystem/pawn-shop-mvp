import { useRouter } from 'expo-router';
import { Keyboard, Pressable, Text, TextInput, View } from 'react-native';
import { useAuth } from '@/src/auth/AuthContext';
import {
  FieldLabel,
  PrimaryButton,
  Screen,
  Subtitle,
  colors,
  fieldStyles,
  numericKeyboardProps,
} from '@/src/components/Screen';
import { usePledgeWizardStore } from '@/src/store/pledgeWizardStore';

export default function ItemDetailsScreen() {
  const router = useRouter();
  const { catalogs } = useAuth();
  const item = usePledgeWizardStore((s) => s.item);
  const patchItem = usePledgeWizardStore((s) => s.patchItem);

  const categories = catalogs?.categories ?? [];
  const brands = catalogs?.brands ?? [];
  const statuses = catalogs?.statuses ?? [];

  const onNext = () => {
    if (!item.name?.trim()) return;
    router.push('/(app)/new-pledge/photos');
  };

  return (
    <Screen>
      <Subtitle>Шаг 3 из 6 — описание предмета</Subtitle>
      <FieldLabel>Название *</FieldLabel>
      <TextInput
        style={fieldStyles.input}
        placeholder="Например: кольцо золото 585"
        value={item.name}
        onChangeText={(name) => patchItem({ name })}
      />
      <FieldLabel>Описание</FieldLabel>
      <TextInput
        style={[fieldStyles.input, { minHeight: 80 }]}
        multiline
        value={item.description ?? ''}
        onChangeText={(description) => patchItem({ description })}
      />
      <FieldLabel>Оценочная стоимость, ₽</FieldLabel>
      <TextInput
        style={fieldStyles.input}
        keyboardType="decimal-pad"
        {...numericKeyboardProps}
        value={item.initial_price != null ? String(item.initial_price) : ''}
        onChangeText={(v) =>
          patchItem({ initial_price: v ? parseFloat(v.replace(',', '.')) : null })
        }
      />
      <FieldLabel>Категория</FieldLabel>
      <PickerRow
        items={categories}
        selectedId={item.category_id ?? null}
        onSelect={(category_id) => patchItem({ category_id })}
      />
      <FieldLabel>Бренд</FieldLabel>
      <PickerRow
        items={brands}
        selectedId={item.brand_id ?? null}
        onSelect={(brand_id) => patchItem({ brand_id })}
      />
      <FieldLabel>Статус</FieldLabel>
      <PickerRow
        items={statuses}
        selectedId={item.status_id ?? null}
        onSelect={(status_id) => patchItem({ status_id })}
      />
      <View style={{ marginTop: 8 }}>
        <PrimaryButton label="Далее: фото" onPress={onNext} disabled={!item.name?.trim()} />
      </View>
    </Screen>
  );
}

function PickerRow({
  items,
  selectedId,
  onSelect,
}: {
  items: { id: number; name: string }[];
  selectedId: number | null;
  onSelect: (id: number) => void;
}) {
  return (
    <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 12 }}>
      {items.map((it) => (
        <Pressable
          key={it.id}
          onPress={() => {
            Keyboard.dismiss();
            onSelect(it.id);
          }}
        >
          <View
            style={{
              paddingHorizontal: 12,
              paddingVertical: 8,
              borderRadius: 8,
              backgroundColor: selectedId === it.id ? colors.primary : '#fff',
              borderWidth: 1,
              borderColor: colors.border,
            }}
          >
            <Text style={{ color: selectedId === it.id ? '#fff' : colors.text }}>{it.name}</Text>
          </View>
        </Pressable>
      ))}
    </View>
  );
}
