-- Выдать пользователю lmb право чтения (SELECT) всех таблиц в базе lmb, схема public.
-- Схема public по умолчанию доступна всем; нужны только права на таблицы.
--
-- Запуск: от суперпользователя или владельца таблиц, к базе lmb:
--   psql -h 192.168.7.250 -p 5432 -U postgres -d lmb -f scripts/grant_lmb_select.sql

\echo 'Database: lmb. Granting SELECT on all tables in schema public to lmb...'

GRANT SELECT ON ALL TABLES IN SCHEMA public TO lmb;
GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO lmb;

\echo 'Done. Check: psql -h 192.168.7.250 -p 5432 -U lmb -d lmb -c "SELECT COUNT(*) FROM public._acc38;"'
