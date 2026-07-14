import { useEffect, useState } from 'react';
import { Button, Pressable, StyleSheet, Text, View } from 'react-native';
import { GestureHandlerRootView, TouchableOpacity } from 'react-native-gesture-handler';

/**
 * Минимальный экран диагностики. Секунды должны тикать сами (без касаний).
 * Если секунды идут, а счётчик нет — блокируются только touch-события.
 */
export default function TouchDebugApp() {
  const [count, setCount] = useState(0);
  const [seconds, setSeconds] = useState(0);

  const bump = () => {
    console.log('[touch-debug] TAP');
    setCount((n) => n + 1);
  };

  useEffect(() => {
    console.log('[touch-debug] mounted');
    const id = setInterval(() => {
      setSeconds((s) => {
        const next = s + 1;
        if (next % 5 === 0) console.log('[touch-debug] tick', next);
        return next;
      });
    }, 1000);
    return () => clearInterval(id);
  }, []);

  return (
    <GestureHandlerRootView style={styles.flex}>
      <View style={styles.root}>
        <Text style={styles.title}>TOUCH DEBUG</Text>

        <Text style={styles.clockLabel}>Таймер JS (без касаний):</Text>
        <Text style={styles.clock}>{seconds} сек</Text>
        <Text style={styles.clockHint}>
          {seconds > 0 ? 'JS работает ✓' : 'Ждём 1 сек…'}
        </Text>

        <Text style={styles.countLabel}>Касания:</Text>
        <Text style={styles.count}>{count}</Text>

        <TouchableOpacity style={styles.pressable} onPress={bump} activeOpacity={0.7}>
          <Text style={styles.pressableText}>RNGH TouchableOpacity +1</Text>
        </TouchableOpacity>

        <Pressable style={styles.pressableRn} onPress={bump}>
          <Text style={styles.pressableText}>RN Pressable +1</Text>
        </Pressable>

        <Button title="RN Button +1" onPress={bump} />

        <Text style={styles.footer}>
          Expo Go → встряхнуть → выключить Remote JS Debugging. Смотрите терминал Mac: [touch-debug] TAP / tick
        </Text>
      </View>
    </GestureHandlerRootView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  root: {
    flex: 1,
    backgroundColor: '#ff6b6b',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  title: { fontSize: 20, fontWeight: '800', color: '#1a1a1a', marginBottom: 12 },
  clockLabel: { fontSize: 14, color: '#1a1a1a' },
  clock: { fontSize: 48, fontWeight: '900', color: '#fff', marginVertical: 4 },
  clockHint: { fontSize: 14, color: '#1a1a1a', marginBottom: 16 },
  countLabel: { fontSize: 14, color: '#1a1a1a' },
  count: { fontSize: 56, fontWeight: '900', color: '#224d66', marginBottom: 16 },
  pressable: {
    backgroundColor: '#224d66',
    paddingHorizontal: 24,
    paddingVertical: 14,
    borderRadius: 10,
    marginBottom: 10,
  },
  pressableRn: {
    backgroundColor: '#333',
    paddingHorizontal: 24,
    paddingVertical: 14,
    borderRadius: 10,
    marginBottom: 10,
  },
  pressableText: { color: '#fff', fontSize: 16, fontWeight: '700' },
  footer: {
    marginTop: 20,
    fontSize: 12,
    color: '#1a1a1a',
    textAlign: 'center',
    lineHeight: 18,
    paddingHorizontal: 8,
  },
});
