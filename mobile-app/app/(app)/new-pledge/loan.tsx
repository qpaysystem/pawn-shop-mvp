import { useRouter } from 'expo-router';
import { Text, TextInput, View } from 'react-native';
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
import { usePledgeWizardStore } from '@/src/store/pledgeWizardStore';
import { calculateBuybackAmount, formatMoney } from '@/src/utils/loan';

export default function LoanTermsScreen() {
  const router = useRouter();
  const loan = usePledgeWizardStore((s) => s.loan);
  const patchLoan = usePledgeWizardStore((s) => s.patchLoan);

  const amount = loan.loan_amount ?? 0;
  const percent = loan.loan_percent ?? 20;
  const buyback = calculateBuybackAmount(amount, percent);

  const onNext = () => {
    if (!amount || amount <= 0) return;
    router.push('/(app)/new-pledge/review');
  };

  return (
    <Screen>
      <Subtitle>Шаг 5 из 6 — сумма и срок</Subtitle>
      <FieldLabel>Сумма займа, ₽ *</FieldLabel>
      <TextInput
        style={fieldStyles.input}
        keyboardType="decimal-pad"
        {...numericKeyboardProps}
        value={amount ? String(amount) : ''}
        onChangeText={(v) =>
          patchLoan({ loan_amount: v ? parseFloat(v.replace(',', '.')) : undefined })
        }
      />
      <FieldLabel>Процент, %</FieldLabel>
      <TextInput
        style={fieldStyles.input}
        keyboardType="decimal-pad"
        {...numericKeyboardProps}
        value={String(percent)}
        onChangeText={(v) =>
          patchLoan({ loan_percent: v ? parseFloat(v.replace(',', '.')) : undefined })
        }
      />
      <FieldLabel>Дата займа (ГГГГ-ММ-ДД)</FieldLabel>
      <TextInput
        style={fieldStyles.input}
        value={loan.loan_date ?? ''}
        onChangeText={(loan_date) => patchLoan({ loan_date })}
      />
      <FieldLabel>Срок до (ГГГГ-ММ-ДД)</FieldLabel>
      <TextInput
        style={fieldStyles.input}
        value={loan.expiry_date ?? ''}
        onChangeText={(expiry_date) => patchLoan({ expiry_date })}
      />
      <Card>
        <Text style={{ color: colors.muted }}>Сумма выкупа</Text>
        <Text style={{ fontSize: 22, fontWeight: '700', color: colors.primary, marginTop: 4 }}>
          {formatMoney(buyback)}
        </Text>
      </Card>
      <View style={{ marginTop: 8 }}>
        <PrimaryButton label="Далее: проверка" onPress={onNext} disabled={!amount || amount <= 0} />
      </View>
    </Screen>
  );
}
