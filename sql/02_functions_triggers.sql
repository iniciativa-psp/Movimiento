-- ============================================================
-- FUNCIONES Y TRIGGERS — PSP
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- FUNCIÓN: Sumar puntos a miembro
-- ────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION sumar_puntos(p_miembro_id uuid, p_puntos integer, p_tipo text DEFAULT 'general', p_descripcion text DEFAULT NULL)
RETURNS void LANGUAGE plpgsql SECURITY DEFINER AS $$
BEGIN
  UPDATE miembros SET puntos_total = puntos_total + p_puntos, updated_at = now()
  WHERE id = p_miembro_id;

  INSERT INTO puntos_historial (miembro_id, puntos, tipo, descripcion)
  VALUES (p_miembro_id, p_puntos, p_tipo, p_descripcion);

  -- Actualizar nivel
  PERFORM actualizar_nivel(p_miembro_id);
END;
$$;

-- ────────────────────────────────────────────────────────────
-- FUNCIÓN: Actualizar nivel de miembro
-- ────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION actualizar_nivel(p_miembro_id uuid)
RETURNS void LANGUAGE plpgsql SECURITY DEFINER AS $$
DECLARE
  v_puntos integer;
  v_nivel  text;
BEGIN
  SELECT puntos_total INTO v_puntos FROM miembros WHERE id = p_miembro_id;

  SELECT nombre INTO v_nivel FROM niveles
  WHERE min_puntos <= v_puntos AND (max_puntos IS NULL OR max_puntos >= v_puntos)
  ORDER BY min_puntos DESC LIMIT 1;

  UPDATE miembros SET nivel = COALESCE(v_nivel, 'Simpatizante') WHERE id = p_miembro_id;
END;
$$;

-- ────────────────────────────────────────────────────────────
-- FUNCIÓN: Dashboard KPIs
-- ────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION get_dashboard_kpis(p_tenant_id text DEFAULT 'panama')
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER AS $$
DECLARE
  v_result jsonb;
  v_miembros bigint;
  v_recaudado numeric;
  v_referidos bigint;
  v_hoy bigint;
  v_provincias bigint;
  v_paises bigint;
BEGIN
  SELECT COUNT(*) INTO v_miembros   FROM miembros WHERE tenant_id = p_tenant_id AND estado = 'activo';
  SELECT COALESCE(SUM(monto),0) INTO v_recaudado FROM pagos p
    JOIN miembros m ON p.miembro_id = m.id
    WHERE m.tenant_id = p_tenant_id AND p.estado = 'completado';
  SELECT COUNT(*) INTO v_referidos  FROM referidos_log rl
    JOIN miembros m ON rl.referidor_id = m.id WHERE m.tenant_id = p_tenant_id;
  SELECT COUNT(*) INTO v_hoy        FROM miembros WHERE tenant_id = p_tenant_id AND DATE(created_at) = CURRENT_DATE;
  SELECT COUNT(DISTINCT provincia_id) INTO v_provincias FROM miembros WHERE tenant_id = p_tenant_id AND estado = 'activo' AND provincia_id IS NOT NULL;
  SELECT COUNT(DISTINCT pais_id) INTO v_paises FROM miembros WHERE tenant_id = p_tenant_id AND estado = 'activo';

  SELECT jsonb_build_object(
    'total_miembros',    v_miembros,
    'total_recaudado',   v_recaudado,
    'total_referidos',   v_referidos,
    'nuevos_hoy',        v_hoy,
    'provincias_activas',v_provincias,
    'paises_activos',    v_paises,
    'por_tipo', (SELECT jsonb_object_agg(tipo_miembro, cnt) FROM (
                  SELECT tipo_miembro, COUNT(*) as cnt FROM miembros
                  WHERE tenant_id = p_tenant_id AND estado = 'activo'
                  GROUP BY tipo_miembro) t),
    'crecimiento_diario', (SELECT jsonb_agg(row_to_json(r)) FROM (
                  SELECT DATE(created_at) as fecha, COUNT(*) as total
                  FROM miembros WHERE tenant_id = p_tenant_id
                  GROUP BY DATE(created_at) ORDER BY fecha DESC LIMIT 30) r)
  ) INTO v_result;

  RETURN v_result;
END;
$$;

-- ────────────────────────────────────────────────────────────
-- FUNCIÓN: Ranking por tipo
-- ────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION get_ranking(p_tipo text DEFAULT 'provincia', p_limit integer DEFAULT 20)
RETURNS TABLE(nombre text, total bigint, monto_total numeric) LANGUAGE plpgsql SECURITY DEFINER AS $$
BEGIN
  IF p_tipo = 'provincia' THEN
    RETURN QUERY
      SELECT t.nombre, COUNT(m.id)::bigint as total, COALESCE(SUM(p.monto),0) as monto_total
      FROM miembros m
      JOIN territorios t ON m.provincia_id = t.id
      LEFT JOIN pagos p ON p.miembro_id = m.id AND p.estado = 'completado'
      WHERE m.estado = 'activo'
      GROUP BY t.id, t.nombre ORDER BY total DESC LIMIT p_limit;

  ELSIF p_tipo = 'pais' THEN
    RETURN QUERY
      SELECT m.pais_id::text as nombre, COUNT(m.id)::bigint as total, COALESCE(SUM(p.monto),0) as monto_total
      FROM miembros m
      LEFT JOIN pagos p ON p.miembro_id = m.id AND p.estado = 'completado'
      WHERE m.estado = 'activo'
      GROUP BY m.pais_id ORDER BY total DESC LIMIT p_limit;

  ELSIF p_tipo = 'embajador' THEN
    RETURN QUERY
      SELECT m.nombre, COUNT(rl.referido_id)::bigint as total, 0::numeric as monto_total
      FROM miembros m
      JOIN referidos_log rl ON rl.referidor_id = m.id
      GROUP BY m.id, m.nombre ORDER BY total DESC LIMIT p_limit;
  END IF;
END;
$$;

-- ────────────────────────────────────────────────────────────
-- FUNCIÓN: Puntos del mapa
-- ────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION get_mapa_puntos(p_tenant_id text DEFAULT 'panama')
RETURNS TABLE(nombre text, lat numeric, lng numeric, total bigint) LANGUAGE plpgsql SECURITY DEFINER AS $$
BEGIN
  RETURN QUERY
    SELECT t.nombre, t.lat, t.lng, COUNT(m.id)::bigint as total
    FROM territorios t
    JOIN miembros m ON m.provincia_id = t.id
    WHERE m.tenant_id = p_tenant_id AND m.estado = 'activo'
      AND t.tipo = 'provincia' AND t.lat IS NOT NULL
    GROUP BY t.id, t.nombre, t.lat, t.lng
    ORDER BY total DESC;
END;
$$;

-- ────────────────────────────────────────────────────────────
-- FUNCIÓN: Mis referidos
-- ────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION get_mis_referidos(p_miembro_id uuid)
RETURNS TABLE(nombre text, estado text, puntos_ganados integer, created_at timestamptz) LANGUAGE plpgsql SECURITY DEFINER AS $$
BEGIN
  RETURN QUERY
    SELECT mr.nombre, mr.estado, rl.puntos_ganados, rl.created_at
    FROM referidos_log rl
    JOIN miembros mr ON rl.referido_id = mr.id
    WHERE rl.referidor_id = p_miembro_id
    ORDER BY rl.created_at DESC;
END;
$$;

-- ────────────────────────────────────────────────────────────
-- TRIGGER: updated_at automático
-- ────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN NEW.updated_at = now(); RETURN NEW; END;
$$;

CREATE TRIGGER trg_miembros_updated   BEFORE UPDATE ON miembros   FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER trg_pagos_updated      BEFORE UPDATE ON pagos       FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ────────────────────────────────────────────────────────────
-- TRIGGER: Pago completado → activar miembro + puntos
-- ────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION trigger_pago_completado()
RETURNS TRIGGER LANGUAGE plpgsql SECURITY DEFINER AS $$
BEGIN
  IF NEW.estado = 'completado' AND OLD.estado != 'completado' THEN
    -- Activar miembro
    UPDATE miembros SET estado = 'activo' WHERE id = NEW.miembro_id;
    -- Sumar puntos (100 pts por $1)
    PERFORM sumar_puntos(NEW.miembro_id, (NEW.monto * 100)::integer, 'pago', 'Pago de membresía');
    -- Registrar en ERP
    INSERT INTO erp_transacciones (tipo, categoria, descripcion, monto, pago_id, cuenta_credito, cuenta_debito)
    VALUES ('ingreso', 'membresia', 'Membresía ' || COALESCE(NEW.tipo_membresia,''), NEW.monto, NEW.id, '1001', '4001');
  END IF;
  RETURN NEW;
END;
$$;

CREATE TRIGGER trg_pago_completado
  AFTER UPDATE ON pagos FOR EACH ROW
  EXECUTE FUNCTION trigger_pago_completado();

-- ────────────────────────────────────────────────────────────
-- RLS Policies
-- ────────────────────────────────────────────────────────────
ALTER TABLE miembros   ENABLE ROW LEVEL SECURITY;
ALTER TABLE pagos       ENABLE ROW LEVEL SECURITY;
ALTER TABLE facturas    ENABLE ROW LEVEL SECURITY;

-- Miembro solo ve su propia data
CREATE POLICY "miembro_select_own" ON miembros FOR SELECT
  USING (user_id = auth.uid() OR auth.role() = 'service_role');
CREATE POLICY "miembro_update_own" ON miembros FOR UPDATE
  USING (user_id = auth.uid());
-- Pagos propios
CREATE POLICY "pago_select_own" ON pagos FOR SELECT
  USING (miembro_id IN (SELECT id FROM miembros WHERE user_id = auth.uid()) OR auth.role() = 'service_role');
-- Ranking público (anon)
CREATE POLICY "ranking_public" ON ranking FOR SELECT USING (true);
-- KPIs públicos
CREATE POLICY "miembros_public_count" ON miembros FOR SELECT USING (true);

-- Tablas públicas (lectura)
ALTER TABLE territorios  ENABLE ROW LEVEL SECURITY;
ALTER TABLE ranking      ENABLE ROW LEVEL SECURITY;
ALTER TABLE configuracion ENABLE ROW LEVEL SECURITY;
CREATE POLICY "territorios_public" ON territorios    FOR SELECT USING (true);
CREATE POLICY "config_public"      ON configuracion  FOR SELECT USING (true);

