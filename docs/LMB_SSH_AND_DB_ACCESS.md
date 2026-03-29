# Доступ к серверу 192.168.7.250 и выдача прав на БД lmb

## SSH

| Параметр   | Значение        |
|-----------|------------------|
| Хост      | 192.168.7.250    |
| Порт      | 22                |
| Пользователь | root          |
| Пароль    | root *(не храните в репозитории)* |

Подключение (с машины, откуда доступен хост, например по VPN):

```bash
ssh root@192.168.7.250
# или с указанием порта:
ssh -p 22 root@192.168.7.250
```

---

## Выдача прав на чтение таблиц пользователю lmb

После входа по SSH на 192.168.7.250 выполните на сервере:

```bash
# Подключение к PostgreSQL (часто postgres на localhost без пароля для root)
psql -U postgres -d lmb -c "GRANT SELECT ON ALL TABLES IN SCHEMA public TO lmb;"
psql -U postgres -d lmb -c "GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO lmb;"
```

Если `psql` запрашивает пароль пользователя `postgres`, укажите его (или настройте `~/.pgpass` на сервере).

Одной строкой (скопировать в терминал на сервере):

```bash
psql -U postgres -d lmb -c "GRANT SELECT ON ALL TABLES IN SCHEMA public TO lmb; GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO lmb;"
```

Либо, если в проекте уже залит скрипт (например по scp):

```bash
psql -U postgres -d lmb -f /path/to/grant_lmb_select.sql
```

---

## Проверка после выдачи прав

С вашей рабочей машины (где запущен проект, с настроенным VPN при необходимости):

```bash
cd /path/to/pawn-shop-mvp
php artisan lmb:try-read --table=_acc38
```

Если вывод содержит «Чтение таблицы public._acc38: OK» — права выданы, пользователь `lmb` может читать данные из БД lmb.
