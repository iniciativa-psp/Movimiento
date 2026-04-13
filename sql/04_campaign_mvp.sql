-- ============================================================
-- PSP — Migración 04: Campaign MVP (Abril–Mayo 2026)
-- Ejecutar en Supabase SQL Editor DESPUÉS de 01, 02 y 03.
-- Campaña: 14 abr 2026 00:00 UTC → 18 may 2026 23:59 UTC
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- 1. Restricciones anti-fraude en miembros
-- ────────────────────────────────────────────────────────────

-- Email único (por tenant)
DO $$ BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'uq_miembros_email_tenant'
  ) THEN
    ALTER TABLE miembros ADD CONSTRAINT uq_miembros_email_tenant UNIQUE (tenant_id, email);
  END IF;
END $$;

-- Celular único (global, independiente del tenant)
DO $$ BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'uq_miembros_celular'
  ) THEN
    ALTER TABLE miembros ADD CONSTRAINT uq_miembros_celular UNIQUE (celular);
  END IF;
END $$;

-- ────────────────────────────────────────────────────────────
-- 2. Restricción anti-fraude en whatsapp_grupos
-- ────────────────────────────────────────────────────────────

-- Link único (evita que el mismo enlace se registre dos veces)
DO $$ BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'uq_wa_grupos_link'
  ) THEN
    ALTER TABLE whatsapp_grupos ADD CONSTRAINT uq_wa_grupos_link UNIQUE (link);
  END IF;
END $$;

-- ────────────────────────────────────────────────────────────
-- 3. Restricción anti-fraude en retos_participacion
-- ────────────────────────────────────────────────────────────

-- Un miembro solo puede completar cada reto una vez
DO $$ BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'uq_retos_particip_miembro_reto'
  ) THEN
    ALTER TABLE retos_participacion
      ADD CONSTRAINT uq_retos_particip_miembro_reto UNIQUE (reto_id, miembro_id);
  END IF;
END $$;

-- ────────────────────────────────────────────────────────────
-- 4. Configuración de campaña en tabla configuracion
-- ────────────────────────────────────────────────────────────

INSERT INTO configuracion (tenant_id, clave, valor, descripcion, tipo)
VALUES
  ('panama', 'campaign_start',   '2026-04-14T00:00:00Z', 'Inicio de la campaña (UTC)',              'fecha'),
  ('panama', 'campaign_end',     '2026-05-18T23:59:59Z', 'Fin de la campaña (UTC)',                 'fecha'),
  ('panama', 'membership_fee',   '1.00',                 'Cuota mínima de membresía (B/.)',         'numero'),
  ('panama', 'meta_miembros',    '1000000',              'Meta de miembros en la campaña',          'numero'),
  ('panama', 'meta_monto',       '1000000',              'Meta de recaudación en B/.',              'numero'),
  ('panama', 'yappy_numero',     '',                     'Número Yappy para recibir pagos',         'texto'),
  ('panama', 'yappy_nombre',     'Panamá Sin Pobreza',   'Nombre en Yappy',                         'texto'),
  ('panama', 'banco_cuenta',     '',                     'Número de cuenta bancaria para ACH',      'texto'),
  ('panama', 'banco_nombre',     '',                     'Nombre del banco',                        'texto'),
  ('panama', 'banco_titular',    '',                     'Nombre del titular de la cuenta',         'texto'),
  ('panama', 'banco_ruc',        '',                     'RUC del titular para transferencias',     'texto'),
  ('panama', 'paypal_email',     '',                     'Email PayPal para recibir pagos',         'texto'),
  ('panama', 'swift_iban',       '',                     'IBAN/SWIFT para transferencias internacionales', 'texto'),
  ('panama', 'puntopago_codigo', '',                     'Código de PuntoPago',                    'texto')
ON CONFLICT (tenant_id, clave) DO NOTHING;

-- ────────────────────────────────────────────────────────────
-- 5. Niveles de gamificación (campaña)
-- ────────────────────────────────────────────────────────────

INSERT INTO niveles (tenant_id, nombre, min_puntos, max_puntos, icono, color)
VALUES
  ('panama', 'Simpatizante',   0,     99,    '🤝', '#6B7280'),
  ('panama', 'Miembro',        100,   499,   '🇵🇦', '#059669'),
  ('panama', 'Activador',      500,   1499,  '⚡', '#2563EB'),
  ('panama', 'Líder',          1500,  4999,  '🌟', '#7C3AED'),
  ('panama', 'Embajador',      5000,  14999, '🏆', '#D97706'),
  ('panama', 'Campeón',        15000, NULL,  '👑', '#DC2626')
ON CONFLICT DO NOTHING;

-- ────────────────────────────────────────────────────────────
-- 6. Retos base de la campaña
-- ────────────────────────────────────────────────────────────

INSERT INTO retos (tenant_id, titulo, descripcion, puntos, tipo, automatico, activo, fecha_inicio, fecha_fin)
VALUES
  ('panama', 'Registro completado',      'Completa tu registro en el movimiento',                               50,  'registro',    true,  true,  '2026-04-14', '2026-05-18'),
  ('panama', 'Primer pago',              'Confirma tu membresía con el pago de B/.1',                          100, 'pago',        true,  true,  '2026-04-14', '2026-05-18'),
  ('panama', 'Primer referido',          'Invita a tu primer amigo/a y que se registre',                        75,  'referido',    true,  true,  '2026-04-14', '2026-05-18'),
  ('panama', '5 referidos',              'Trae a 5 personas al movimiento',                                    200, 'referido_5',  true,  true,  '2026-04-14', '2026-05-18'),
  ('panama', '10 referidos',             'Trae a 10 personas al movimiento',                                   500, 'referido_10', true,  true,  '2026-04-14', '2026-05-18'),
  ('panama', 'Comparte en WhatsApp',     'Comparte tu enlace de referido en WhatsApp',                          25,  'social_wa',   false, true,  '2026-04-14', '2026-05-18'),
  ('panama', 'Perfil completo',          'Completa todos los datos de tu perfil incluyendo foto',               50,  'perfil',      false, true,  '2026-04-14', '2026-05-18'),
  ('panama', 'Activo 7 días seguidos',   'Inicia sesión 7 días consecutivos durante la campaña',               150, 'streak_7',    true,  true,  '2026-04-14', '2026-05-18')
ON CONFLICT DO NOTHING;

-- ────────────────────────────────────────────────────────────
-- 7. Vista materializada: ranking de miembros por puntos
-- ────────────────────────────────────────────────────────────

CREATE MATERIALIZED VIEW IF NOT EXISTS ranking_mv AS
SELECT
    m.id                  AS miembro_id,
    m.tenant_id,
    m.nombre,
    m.puntos_total,
    m.nivel,
    m.provincia_id,
    m.pais_id,
    m.avatar_url,
    RANK() OVER (PARTITION BY m.tenant_id ORDER BY m.puntos_total DESC)
                          AS posicion_nacional,
    RANK() OVER (PARTITION BY m.tenant_id, m.provincia_id ORDER BY m.puntos_total DESC)
                          AS posicion_provincial,
    RANK() OVER (PARTITION BY m.tenant_id, m.pais_id ORDER BY m.puntos_total DESC)
                          AS posicion_pais
FROM miembros m
WHERE m.estado = 'activo';

CREATE UNIQUE INDEX IF NOT EXISTS idx_ranking_mv_miembro ON ranking_mv(miembro_id);
CREATE INDEX IF NOT EXISTS idx_ranking_mv_nacional   ON ranking_mv(tenant_id, posicion_nacional);
CREATE INDEX IF NOT EXISTS idx_ranking_mv_provincial ON ranking_mv(tenant_id, provincia_id, posicion_provincial);

-- Función para refrescar la vista materializada
CREATE OR REPLACE FUNCTION refresh_ranking_mv()
RETURNS void LANGUAGE plpgsql SECURITY DEFINER AS $$
BEGIN
  REFRESH MATERIALIZED VIEW CONCURRENTLY ranking_mv;
END;
$$;

-- ────────────────────────────────────────────────────────────
-- 8. Función: obtener ranking de un miembro (desde ranking_mv)
-- ────────────────────────────────────────────────────────────

CREATE OR REPLACE FUNCTION get_member_rank(p_miembro_id uuid)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER AS $$
DECLARE
  v_row ranking_mv%ROWTYPE;
BEGIN
  SELECT * INTO v_row FROM ranking_mv WHERE miembro_id = p_miembro_id;

  IF NOT FOUND THEN
    RETURN jsonb_build_object(
      'posicion_nacional',   NULL,
      'posicion_provincial', NULL,
      'posicion_pais',       NULL,
      'puntos_total',        0
    );
  END IF;

  RETURN jsonb_build_object(
    'posicion_nacional',   v_row.posicion_nacional,
    'posicion_provincial', v_row.posicion_provincial,
    'posicion_pais',       v_row.posicion_pais,
    'puntos_total',        v_row.puntos_total,
    'nivel',               v_row.nivel
  );
END;
$$;

-- ────────────────────────────────────────────────────────────
-- 9. Trigger: actualizar ranking al cambiar puntos del miembro
-- ────────────────────────────────────────────────────────────

CREATE OR REPLACE FUNCTION trigger_refresh_ranking()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER AS $$
BEGIN
  -- Refrescar de forma asíncrona si pg_notify está disponible
  PERFORM pg_notify('ranking_refresh', NEW.id::text);
  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_ranking_refresh ON miembros;
CREATE TRIGGER trg_ranking_refresh
  AFTER UPDATE OF puntos_total ON miembros
  FOR EACH ROW
  WHEN (OLD.puntos_total IS DISTINCT FROM NEW.puntos_total)
  EXECUTE FUNCTION trigger_refresh_ranking();

-- ────────────────────────────────────────────────────────────
-- 10. Insertar datos de la vista en la tabla ranking (compatibilidad)
-- ────────────────────────────────────────────────────────────

-- Asegurar que ranking tiene las columnas que usa el plugin
DO $$ BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_name = 'ranking' AND column_name = 'posicion_nacional'
  ) THEN
    ALTER TABLE ranking ADD COLUMN posicion_nacional  integer;
    ALTER TABLE ranking ADD COLUMN posicion_provincial integer;
    ALTER TABLE ranking ADD COLUMN posicion_pais      integer;
  END IF;
END $$;

-- ────────────────────────────────────────────────────────────
-- 11. Row Level Security en ranking_mv (solo lectura pública)
-- ────────────────────────────────────────────────────────────

-- Las vistas materializadas no soportan RLS directamente,
-- pero restringimos acceso via policies en la tabla base (miembros).
-- Para el dashboard público creamos una vista separada con datos anónimos:

CREATE OR REPLACE VIEW ranking_publico AS
SELECT
    r.miembro_id,
    r.nombre,
    r.puntos_total,
    r.nivel,
    r.posicion_nacional,
    r.posicion_provincial,
    r.posicion_pais,
    t.nombre AS provincia_nombre
FROM ranking_mv r
LEFT JOIN territorios t ON t.id = r.provincia_id
WHERE r.tenant_id = 'panama'
ORDER BY r.posicion_nacional;

-- ────────────────────────────────────────────────────────────
-- 12. Función: asignar grupo WhatsApp al miembro por territorio
-- ────────────────────────────────────────────────────────────

CREATE OR REPLACE FUNCTION get_wa_group_for_member(p_miembro_id uuid)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER AS $$
DECLARE
  v_miembro miembros%ROWTYPE;
  v_grupo   whatsapp_grupos%ROWTYPE;
BEGIN
  SELECT * INTO v_miembro FROM miembros WHERE id = p_miembro_id;

  -- Buscar por comunidad → corregimiento → distrito → provincia (más específico primero)
  SELECT * INTO v_grupo FROM whatsapp_grupos
  WHERE activo = true AND tipo = 'territorial'
    AND territorio_id IN (
      v_miembro.comunidad_id,
      v_miembro.corregimiento_id,
      v_miembro.distrito_id,
      v_miembro.provincia_id
    )
  ORDER BY
    CASE territorio_id
      WHEN v_miembro.comunidad_id      THEN 1
      WHEN v_miembro.corregimiento_id  THEN 2
      WHEN v_miembro.distrito_id       THEN 3
      WHEN v_miembro.provincia_id      THEN 4
      ELSE 5
    END,
    miembros_actual ASC
  LIMIT 1;

  IF NOT FOUND THEN
    RETURN NULL;
  END IF;

  RETURN jsonb_build_object(
    'id',              v_grupo.id,
    'nombre',          v_grupo.nombre,
    'link',            v_grupo.link,
    'tipo',            v_grupo.tipo,
    'miembros_actual', v_grupo.miembros_actual,
    'miembros_max',    v_grupo.miembros_max
  );
END;
$$;

-- ────────────────────────────────────────────────────────────
-- 13. RLS policies adicionales
-- ────────────────────────────────────────────────────────────

-- whatsapp_grupos: cualquiera puede leer, solo service_role puede escribir
ALTER TABLE whatsapp_grupos ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "wa_grupos_read_all"  ON whatsapp_grupos;
DROP POLICY IF EXISTS "wa_grupos_write_svc" ON whatsapp_grupos;

CREATE POLICY "wa_grupos_read_all"  ON whatsapp_grupos FOR SELECT USING (activo = true);
CREATE POLICY "wa_grupos_write_svc" ON whatsapp_grupos FOR ALL   USING (auth.role() = 'service_role');

-- retos: lectura pública
ALTER TABLE retos ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "retos_read_all" ON retos;
CREATE POLICY "retos_read_all" ON retos FOR SELECT USING (activo = true);
