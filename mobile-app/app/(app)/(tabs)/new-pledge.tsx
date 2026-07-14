import { useFocusEffect } from 'expo-router';
import { useCallback } from 'react';
import WizardStartScreen from '@/src/screens/WizardStartScreen';
import { usePledgeWizardStore } from '@/src/store/pledgeWizardStore';

/** Вкладка «Приём» — шаг 1 мастера без редиректа (раньше висел спиннер). */
export default function NewPledgeTabScreen() {
  const reset = usePledgeWizardStore((s) => s.reset);

  useFocusEffect(
    useCallback(() => {
      reset();
    }, [reset]),
  );

  return <WizardStartScreen />;
}
