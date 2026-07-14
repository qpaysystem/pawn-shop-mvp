# Expo Go + iPhone: две разные сети

## Почему «не работают касания»

Часто это **не баг формы**, а **нет связи iPhone → Metro на Mac** (порт 8081).

Приложение выглядит как логин, но JS **заморожен** — поля не нажимаются, экран может казаться затемнённым.

**Проверка:** на экране входа в dev-блоке строка **«Metro / JS: работает (N сек)»** — цифра **должна расти каждую секунду**. Если стоит на месте или «нет связи с Mac» — сначала чините Metro, не UI.

## Если на iPhone: «Профиль приложения „Ломбард“ недоступен»

Это **не ошибка API**, а истёкший **dev provisioning profile** у нативной сборки (Xcode), установленной на телефон. Так бывает каждые ~7 дней (бесплатный Apple ID) или после смены сертификата.

**Исправление:**

1. Удалите иконку **«Ломбард»** с iPhone (долгое нажатие → удалить).
2. На Mac подключите iPhone по USB, разблокируйте, «Доверять этому компьютеру».
3. Выберите адрес API и пересоберите:

```bash
# LTE / вне дома (белый IP, порт 18082)
./scripts/set-mobile-env-wan.sh

# Дома через Tailscale → 192.168.1.67:8000
./scripts/set-mobile-env-home-lan.sh

./scripts/rebuild-ios-device.sh
```

4. На iPhone при первом запуске: **Настройки → Основные → VPN и управление устройством** → доверить разработчику.

**Альтернатива без Xcode:** удалить «Ломбард», поставить **Expo Go** из App Store, на Mac `cd mobile-app && npm run start:phone`, отсканировать QR.

## Две связи

| Что | Откуда | Как |
|-----|--------|-----|
| **API** (логин, данные) | Домашний сервер `192.168.1.67:8000` | Tailscale + subnet `192.168.1.0/24` через **3apa3aserver** |
| **Metro** (JS, UI, касания) | Mac, порт **8081** | Надёжно: **`npm run start:phone`** (`--tunnel`) |

API через Tailscale **уже работает** (Safari открывает `192.168.1.67`).

Metro через `start:tailscale` / LAN часто **не доходит** до iPhone → «мёртвый» экран.

## Запуск (рекомендуется)

**Терминал на Mac:**

```bash
cd mobile-app
npm run start:phone
```

Дождитесь QR с адресом **`*.exp.direct`** или `tunnel`, не `192.168.x.x`.

**iPhone:**

1. Tailscale Connected (для API).
2. Полностью закройте **Expo Go** → откройте → **новый QR**.
3. Встряхните → **Remote JS Debugging** выключен.
4. На логине: **«Metro / JS: работает (5 сек…)»** — цифра растёт.

## Если tunnel не стартует

Пакет должен быть **в проекте** (не только глобально):

```bash
cd mobile-app
npx expo install @expo/ngrok
npm run start:phone
```

Если снова `Install @expo/ngrok` — не ставьте `-g`, только команду выше в `mobile-app`.

## Альтернатива

`npm run start:tailscale` — только если iPhone пингует Mac по Tailscale **и** порт 8081 открыт на Mac.

## Metro на домашнем сервере (опционально)

На `vm-web-host-01` **нет npm** по умолчанию, а `deploy-home-lan.sh` **не копирует** `mobile-app/` (там только обрывок без `package.json`).

С Mac один раз:

```bash
./scripts/sync-mobile-app-to-home.sh
./scripts/setup-home-expo-dev.sh   # Node 20 + npm install на сервере
```

На сервере (порт **8082** — 8081 занят Docker; **tunnel** с хоста часто падает по таймауту ngrok):

```bash
export NVM_DIR="$HOME/.nvm" && . "$NVM_DIR/nvm.sh"
cd /opt/stacks/prod/lombard-portal/app/mobile-app
CI=1 EXPO_NO_DEPENDENCY_VALIDATION=1 REACT_NATIVE_PACKAGER_HOSTNAME=192.168.1.67 \
  npx expo start --host lan --port 8082
```

С Mac: `./scripts/start-expo-on-home.sh` — Metro в фоне, лог `~/expo-metro.log`.

**Expo Go:** `exp://192.168.1.67:8082` (iPhone в Tailscale, subnet `192.168.1.0/24`).

`tailscale ip -4` на `vm-web-host-01` **не установлен** — используйте LAN-адрес `192.168.1.67`.

Для ежедневной разработки проще оставить Metro на Mac (см. выше).
