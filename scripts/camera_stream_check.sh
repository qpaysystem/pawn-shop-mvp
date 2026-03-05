#!/usr/bin/env bash
# Проверка доступа к видеопотоку с камеры/регистратора
# Запускать с машины, находящейся в той же сети, что и 192.168.7.7

HOST="${1:-192.168.7.7}"
PORT="${2:-9780}"
USER="${3:-all}"
PASS="${4:-all}"
BASE="http://${HOST}:${PORT}"

echo "=== Проверка устройства ${HOST}:${PORT} ==="
echo ""

# 1. Доступность
echo "1. Доступность (HTTP)..."
if curl -s -o /dev/null -w "%{http_code}" --connect-timeout 5 "${BASE}/" > /tmp/cam_code 2>/dev/null; then
    echo "   Ответ: $(cat /tmp/cam_code)"
else
    echo "   Не удалось подключиться. Убедитесь, что вы в той же сети, что и камера."
    exit 1
fi

# 2. Главная страница (первые строки)
echo ""
echo "2. Главная страница (начало):"
curl -s -u "${USER}:${PASS}" --connect-timeout 5 "${BASE}/" 2>/dev/null | head -50

# 3. Типичные пути видеопотоков для IP-камер и DVR/NVR
echo ""
echo "3. Проверка типичных путей потока:"
for path in "/" "/live" "/video" "/stream" "/mjpg/video.mjpg" "/cgi-bin/mjpg/video.cgi" "/axis-cgi/mjpg/video.cgi" "/Streaming/Channels/101" "/api/v1/stream" "/cgi/mjpg/mjpeg.cgi" "/img/mjpeg.cgi" "/mjpeg.cgi" "/videostream.cgi" "/img/video.mjpeg" "/mobile/" "/live/main" "/ISAPI/Streaming/channels/101"; do
    code=$(curl -s -o /dev/null -w "%{http_code}" -u "${USER}:${PASS}" --connect-timeout 2 "${BASE}${path}" 2>/dev/null)
    if [ "$code" != "000" ] && [ -n "$code" ]; then
        echo "   ${path} -> HTTP $code"
    fi
done

# 4. MyVMS / Line Server — видеопотоки на порту 9786 (HLS)
echo ""
echo "4. MyVMS потоки (порт 9786):"
STREAM_PORT=9786
STREAM_BASE="http://${HOST}:${STREAM_PORT}"
for cam in 0 1 2; do
    for stream in "main.m3u8" "sub.m3u8"; do
        code=$(curl -s -o /dev/null -w "%{http_code}" -u "${USER}:${PASS}" --connect-timeout 3 "${STREAM_BASE}/cameras/${cam}/streaming/${stream}" 2>/dev/null)
        if [ "$code" = "200" ]; then
            echo "   Камера ${cam} ${stream}: OK"
            echo "   URL: ${STREAM_BASE}/cameras/${cam}/streaming/${stream}"
        fi
    done
done

# 5. RTSP (если есть ffprobe/ffmpeg) — MyVMS по умолчанию порт 9784
echo ""
echo "5. RTSP (порт 9784 для MyVMS, 554 для других):"
for rtsp_path in "rtsp://${USER}:${PASS}@${HOST}:9784/cameras/0/streaming/main" "rtsp://${USER}:${PASS}@${HOST}:554/stream1"; do
    if command -v ffprobe >/dev/null 2>&1; then
        if ffprobe -v error -rtsp_transport tcp "${rtsp_path}" 2>&1 | head -3 | grep -q "Stream"; then
            echo "   Рабочий RTSP: ${rtsp_path}"
        fi
    else
        echo "   (ffprobe не установлен, пропуск RTSP)"
        break
    fi
done

echo ""
echo "Готово."
echo "Просмотр: веб-интерфейс ${BASE}/ (логин ${USER})."
echo "Поток HLS (для плеера/браузера): ${STREAM_BASE}/cameras/0/streaming/main.m3u8 (логин ${USER})."
