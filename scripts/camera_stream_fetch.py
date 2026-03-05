#!/usr/bin/env python3
"""
Получить информацию о видеопотоке с камеры и при возможности — один кадр.
Запускать с машины в той же сети, что и камера.

Использование:
  python3 camera_stream_fetch.py
  python3 camera_stream_fetch.py 192.168.7.7 6780 admin adlmin
"""
import sys
import urllib.request
import urllib.error
from urllib.parse import urljoin

HOST = sys.argv[1] if len(sys.argv) > 1 else "192.168.7.7"
PORT = sys.argv[2] if len(sys.argv) > 2 else "9780"
USER = sys.argv[3] if len(sys.argv) > 3 else "admin"
PASS = sys.argv[4] if len(sys.argv) > 4 else "adlmin"

BASE = f"http://{HOST}:{PORT}"


def main():
    password_mgr = urllib.request.HTTPPasswordMgrWithDefaultRealm()
    password_mgr.add_password(None, BASE, USER, PASS)
    handler = urllib.request.HTTPBasicAuthHandler(password_mgr)
    opener = urllib.request.build_opener(handler)
    urllib.request.install_opener(opener)

    print(f"Подключение к {BASE}")
    print()

    # Заголовки главной страницы
    try:
        req = urllib.request.Request(BASE)
        req.add_header("User-Agent", "Mozilla/5.0")
        with opener.open(req, timeout=5) as r:
            info = r.info()
            print("Заголовки ответа главной страницы:")
            for k, v in info.items():
                print(f"  {k}: {v}")
            ct = info.get("Content-Type", "")
            print()
            body = r.read(2000)
            if body:
                try:
                    print("Начало тела (текст):", body[:500].decode("utf-8", errors="replace"))
                except Exception:
                    print("Начало тела (первые байты):", body[:200])
    except urllib.error.URLError as e:
        print("Ошибка подключения:", e)
        print("Убедитесь, что вы в той же сети, что и камера (192.168.7.7).")
        return

    # Попытка получить один кадр из типичного MJPEG-потока
    paths = ["/video", "/stream", "/mjpg/video.mjpg", "/live", "/Streaming/Channels/101"]
    for path in paths:
        url = urljoin(BASE + "/", path.lstrip("/"))
        try:
            req = urllib.request.Request(url)
            req.add_header("User-Agent", "Mozilla/5.0")
            with opener.open(req, timeout=3) as r:
                ct = r.headers.get("Content-Type", "")
                print()
                print(f"Поток найден: {url}")
                print(f"  Content-Type: {ct}")
                if "multipart" in ct or "mjpeg" in ct.lower() or "jpeg" in ct.lower():
                    data = r.read(50000)
                    if data:
                        out = "frame_capture.jpg"
                        # Вырезать первый JPEG из multipart (между boundary)
                        if b"\xff\xd8" in data:
                            start = data.index(b"\xff\xd8")
                            end = data.find(b"\xff\xd9", start) + 2 if start >= 0 else start + 2
                            if end > start:
                                with open(out, "wb") as f:
                                    f.write(data[start:end])
                                print(f"  Сохранён кадр: {out}")
                break
        except (urllib.error.URLError, OSError) as e:
            continue

    print()
    print("Готово.")


if __name__ == "__main__":
    main()
