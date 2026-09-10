-- ==========================================================
-- MIGRACIÓN 002: UNIQUE CONSTRAINT PARA CONCURRENCIA EN EVENT STORE
-- ==========================================================

ALTER TABLE `eventos_asistencia`
    ADD CONSTRAINT `uniq_stream_version`
    UNIQUE (`aggregate_type`, `aggregate_id`, `version`);
