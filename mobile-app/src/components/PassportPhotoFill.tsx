import * as ImagePicker from 'expo-image-picker';
import { useState } from 'react';
import { ActivityIndicator, Alert, Image, Pressable, Text, View } from 'react-native';
import { useAuth } from '@/src/auth/AuthContext';
import { parsePassportApi } from '@/src/api/clients';
import type { PassportParseResult } from '@/src/types/client';
import { Card, FieldLabel, colors } from './Screen';

type Props = {
  onParsed: (result: PassportParseResult) => void;
};

export function PassportPhotoFill({ onParsed }: Props) {
  const { token } = useAuth();
  const [uri, setUri] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [status, setStatus] = useState<string | null>(null);
  const [statusOk, setStatusOk] = useState(false);

  const pick = async (useCamera: boolean) => {
    const perm = useCamera
      ? await ImagePicker.requestCameraPermissionsAsync()
      : await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert('Доступ', 'Разрешите доступ к камере или галерее в настройках');
      return;
    }
    const result = useCamera
      ? await ImagePicker.launchCameraAsync({
          quality: 0.75,
          allowsEditing: false,
        })
      : await ImagePicker.launchImageLibraryAsync({
          quality: 0.75,
          allowsEditing: false,
          mediaTypes: ['images'],
        });
    if (result.canceled || !result.assets?.[0]?.uri) return;
    setUri(result.assets[0].uri);
    setStatus(null);
    setStatusOk(false);
  };

  const onRecognize = async () => {
    if (!uri || !token) return;
    setBusy(true);
    setStatus('Распознавание…');
    setStatusOk(false);
    try {
      const parsed = await parsePassportApi(token, uri);
      onParsed(parsed);
      setStatusOk(true);
      setStatus('Данные заполнены по фото');
    } catch (e) {
      setStatusOk(false);
      setStatus(e instanceof Error ? e.message : 'Ошибка распознавания');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Card style={{ marginBottom: 16, backgroundColor: '#f8fafc' }}>
      <FieldLabel>Паспорт по фото</FieldLabel>
      <Text style={{ color: colors.muted, fontSize: 13, marginBottom: 10 }}>
        Сфотографируйте разворот с ФИО и данными паспорта (чётко, без бликов).
      </Text>
      <View style={{ flexDirection: 'row', gap: 8, marginBottom: 10 }}>
        <Pressable
          onPress={() => pick(true)}
          disabled={busy}
          style={{
            flex: 1,
            paddingVertical: 10,
            borderRadius: 8,
            borderWidth: 1,
            borderColor: colors.primary,
            alignItems: 'center',
          }}
        >
          <Text style={{ color: colors.primary, fontWeight: '600' }}>Камера</Text>
        </Pressable>
        <Pressable
          onPress={() => pick(false)}
          disabled={busy}
          style={{
            flex: 1,
            paddingVertical: 10,
            borderRadius: 8,
            borderWidth: 1,
            borderColor: colors.border,
            alignItems: 'center',
          }}
        >
          <Text style={{ fontWeight: '600' }}>Галерея</Text>
        </Pressable>
      </View>
      {uri ? (
        <Image
          source={{ uri }}
          style={{
            width: '100%',
            height: 140,
            borderRadius: 8,
            marginBottom: 10,
            backgroundColor: '#e2e8f0',
          }}
          resizeMode="cover"
        />
      ) : null}
      <Pressable
        onPress={onRecognize}
        disabled={!uri || busy || !token}
        style={{
          paddingVertical: 12,
          borderRadius: 8,
          backgroundColor: !uri || busy ? colors.muted : colors.primary,
          alignItems: 'center',
        }}
      >
        {busy ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={{ color: '#fff', fontWeight: '600' }}>Распознать и заполнить</Text>
        )}
      </Pressable>
      {status ? (
        <Text
          style={{
            marginTop: 8,
            fontSize: 13,
            color: statusOk ? '#15803d' : colors.danger,
          }}
        >
          {status}
        </Text>
      ) : null}
    </Card>
  );
}
