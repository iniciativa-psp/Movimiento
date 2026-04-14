-- =============================================================================
-- Migración 05: Vincular usuarios WordPress con miembros en Supabase
-- Ejecutar en Supabase Dashboard → SQL Editor → New query
-- Ejecutar DESPUÉS de 04_campaign_mvp.sql
-- =============================================================================

-- Columna para almacenar el ID del usuario WordPress asociado al miembro
ALTER TABLE miembros
  ADD COLUMN IF NOT EXISTS wp_user_id integer;

-- Índice único por tenant para evitar que un usuario WP aparezca dos veces
-- (filtra NULLs para que miembros sin WP user no generen conflicto)
CREATE UNIQUE INDEX IF NOT EXISTS uq_miembros_tenant_wp_user
  ON miembros (tenant_id, wp_user_id)
  WHERE wp_user_id IS NOT NULL;

COMMENT ON COLUMN miembros.wp_user_id
  IS 'ID del usuario WordPress (wp_users.ID) asociado a este miembro. NULL si aún no completó el registro WP.';
