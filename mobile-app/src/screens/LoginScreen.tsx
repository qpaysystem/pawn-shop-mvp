import { useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import {
  Alert,
  KeyboardAvoidingView,
  Linking,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  type TextInput as TextInputType,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { pingApiServer } from '@/src/api/health';
import { useAuth } from '@/src/auth/AuthContext';
import { DevStatusBar } from '@/src/components/DevStatusBar';
import { KeyboardDoneAccessory, colors } from '@/src/components/Screen';
import { env } from '@/src/config/env';
import { formatApiErrorMessage } from '@/src/utils/formatApiError';

const MOCK_EMAIL = 'demo@lombard.local';
const MOCK_PASSWORD = 'demo';
const REAL_EMAIL = 'appraiser@example.com';
const REAL_PASSWORD = 'password';

export function LoginScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const emailRef = useRef<TextInputType>(null);
  const passwordRef = useRef<TextInputType>(null);
  const { login, isAuthenticated } = useAuth();
  const [email, setEmail] = useState(env.useMockAuth ? MOCK_EMAIL : REAL_EMAIL);
  const [password, setPassword] = useState(env.useMockAuth ? MOCK_PASSWORD : REAL_PASSWORD);
  const [busy, setBusy] = useState(false);
  const [pingBusy, setPingBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pingOk, setPingOk] = useState<string | null>(null);

  useEffect(() => {
    if (!password.trim() && !env.useMockAuth) {
      setPassword(REAL_PASSWORD);
    }
  }, [password]);

  useEffect(() => {
    if (isAuthenticated) {
      router.replace('/(app)/(tabs)');
    }
  }, [isAuthenticated, router]);

  const fillTestCredentials = () => {
    setEmail(REAL_EMAIL);
    setPassword(REAL_PASSWORD);
    setError(null);
  };

  const onPing = async () => {
    if (env.useMockAuth) {
      setPingOk('Mock-режим — сервер не нужен');
      return;
    }
    setPingBusy(true);
    setPingOk(null);
    const result = await pingApiServer();
    setPingBusy(false);
    setPingOk(result.ok ? `✓ ${result.message}` : `✗ ${result.message}`);
  };

  const onSubmit = async () => {
    const pwd = password.trim();
    if (!pwd) {
      setError('Введите пароль (ниже кнопка «Подставить тестовый логин»)');
      passwordRef.current?.focus();
      return;
    }
    setError(null);
    setBusy(true);
    try {
      await login({ email: email.trim(), password: pwd });
      router.replace('/(app)/(tabs)');
    } catch (e) {
      const msg = formatApiErrorMessage(e);
      setError(msg);
      Alert.alert('Ошибка входа', msg);
    } finally {
      setBusy(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.flex}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView
        style={styles.flex}
        contentContainerStyle={[
          styles.scroll,
          { paddingTop: insets.top + 12, paddingBottom: insets.bottom + 24 },
        ]}
        keyboardShouldPersistTaps="always"
        keyboardDismissMode="on-drag"
      >
        <Text style={styles.title}>{env.appName}</Text>
        <Text style={styles.subtitle}>Вход для сотрудников</Text>
        <Text style={styles.hint}>
          {env.useMockAuth ? 'Режим: mock' : `API: ${env.apiOrigin}`}
        </Text>

        {!env.useMockAuth ? (
          <View style={styles.warnBox}>
            <Text style={styles.warnText}>
              Дома (Tailscale): http://192.168.1.67:8000{'\n'}
              С LTE / вне дома: http://37.193.61.182:18082{'\n'}
              Адрес задаётся при сборке в mobile-app/.env — см. scripts/set-mobile-env-*.sh
            </Text>
            <Pressable onPress={() => Linking.openURL(env.apiOrigin)}>
              <Text style={styles.link}>Проверить API в Safari →</Text>
            </Pressable>
          </View>
        ) : null}

        {pingOk ? (
          <Text
            style={[
              styles.pingText,
              pingOk.startsWith('✓') ? styles.pingOk : styles.pingFail,
            ]}
          >
            {pingOk}
          </Text>
        ) : null}

        {error ? (
          <View style={styles.errorBox}>
            <Text style={styles.errorText}>{error}</Text>
          </View>
        ) : null}

        {__DEV__ ? <DevStatusBar /> : null}

        <Text style={styles.label}>Email</Text>
        <TextInput
          ref={emailRef}
          style={styles.input}
          value={email}
          onChangeText={setEmail}
          editable={!busy}
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType="email-address"
          placeholder="email"
          placeholderTextColor={colors.muted}
        />

        <Text style={styles.label}>Пароль</Text>
        <TextInput
          ref={passwordRef}
          style={styles.input}
          value={password}
          onChangeText={setPassword}
          editable={!busy}
          secureTextEntry
          autoCapitalize="none"
          returnKeyType="go"
          onSubmitEditing={onSubmit}
          placeholder="password"
          placeholderTextColor={colors.muted}
        />

        <View style={styles.row}>
          <TouchableOpacity
            style={styles.secondaryBtn}
            onPress={fillTestCredentials}
            disabled={busy}
          >
            <Text style={styles.secondaryBtnText}>Подставить тестовый логин</Text>
          </TouchableOpacity>
          {!env.useMockAuth ? (
            <TouchableOpacity
              style={styles.secondaryBtn}
              onPress={onPing}
              disabled={busy || pingBusy}
            >
              <Text style={styles.secondaryBtnText}>
                {pingBusy ? 'Проверка…' : 'Проверить сервер'}
              </Text>
            </TouchableOpacity>
          ) : null}
        </View>

        <TouchableOpacity
          style={[styles.button, busy && styles.buttonDisabled]}
          onPress={onSubmit}
          disabled={busy}
          activeOpacity={0.85}
        >
          <Text style={styles.buttonText}>{busy ? 'Вход…' : 'Войти'}</Text>
        </TouchableOpacity>

        <Text style={styles.footer}>
          {env.useMockAuth
            ? `${MOCK_EMAIL} / ${MOCK_PASSWORD}`
            : `${REAL_EMAIL} / ${REAL_PASSWORD}`}
        </Text>
      </ScrollView>
      <KeyboardDoneAccessory />
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: colors.bg },
  scroll: { flexGrow: 1, paddingHorizontal: 20 },
  title: { fontSize: 26, fontWeight: '700', color: colors.primary, marginBottom: 6 },
  subtitle: { fontSize: 14, color: colors.muted, marginBottom: 8 },
  hint: { fontSize: 13, color: colors.muted, marginBottom: 8 },
  warnBox: {
    backgroundColor: '#fef3c7',
    borderRadius: 8,
    padding: 12,
    marginBottom: 12,
  },
  warnText: { color: '#92400e', fontSize: 13, lineHeight: 18, marginBottom: 8 },
  link: { color: colors.primary, fontSize: 14, fontWeight: '600' },
  pingText: { fontSize: 13, marginBottom: 10, lineHeight: 18 },
  pingOk: { color: '#065f46' },
  pingFail: { color: '#991b1b' },
  errorBox: {
    backgroundColor: '#fee2e2',
    borderRadius: 8,
    padding: 12,
    marginBottom: 12,
  },
  errorText: { color: '#991b1b', fontSize: 14, lineHeight: 20 },
  label: { fontSize: 13, fontWeight: '600', color: colors.muted, marginBottom: 6 },
  input: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 10,
    backgroundColor: '#fff',
    paddingHorizontal: 14,
    paddingVertical: 14,
    fontSize: 17,
    color: colors.text,
    minHeight: 48,
    marginBottom: 12,
  },
  row: { gap: 8, marginBottom: 12 },
  secondaryBtn: {
    paddingVertical: 12,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.primary,
    alignItems: 'center',
  },
  secondaryBtnText: { color: colors.primary, fontSize: 14, fontWeight: '600' },
  button: {
    backgroundColor: colors.primary,
    paddingVertical: 16,
    borderRadius: 10,
    alignItems: 'center',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontSize: 17, fontWeight: '600' },
  footer: { marginTop: 16, fontSize: 13, color: colors.muted, lineHeight: 20 },
});
