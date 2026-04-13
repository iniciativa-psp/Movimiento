-- ============================================================
-- PSP — Tablas adicionales: Pedidos de Productos y SIGS
-- Ejecutar en Supabase SQL Editor después de 01 y 02
-- ============================================================

-- Tabla: pedidos_productos
CREATE TABLE IF NOT EXISTS pedidos_productos (
    id               uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id        text REFERENCES tenants(id) DEFAULT 'panama',
    miembro_id       uuid NOT NULL REFERENCES miembros(id),
    pago_id          uuid REFERENCES pagos(id),
    producto_slug    text NOT NULL,
    producto_nombre  text NOT NULL,
    cantidad         integer NOT NULL DEFAULT 1,
    precio_unitario  numeric(10,2) NOT NULL,
    total            numeric(10,2) NOT NULL,
    referencia       text UNIQUE,
    estado           text DEFAULT 'pendiente_pago'
                     CHECK (estado IN ('pendiente_pago','pagado','en_proceso','entregado','cancelado')),
    notas            text,
    created_at       timestamptz DEFAULT now(),
    updated_at       timestamptz DEFAULT now()
);
CREATE INDEX idx_pedidos_miembro ON pedidos_productos(miembro_id);
CREATE INDEX idx_pedidos_estado  ON pedidos_productos(estado);

ALTER TABLE pedidos_productos ENABLE ROW LEVEL SECURITY;
CREATE POLICY "pedidos_own" ON pedidos_productos FOR SELECT
    USING (miembro_id IN (SELECT id FROM miembros WHERE user_id = auth.uid())
           OR auth.role() = 'service_role');

-- Tabla: sigs_solicitudes
CREATE TABLE IF NOT EXISTS sigs_solicitudes (
    id             uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id      text REFERENCES tenants(id) DEFAULT 'panama',
    nombre         text NOT NULL,
    email          text NOT NULL,
    celular        text,
    organizacion   text,
    plan           text DEFAULT 'estandar',
    descripcion    text,
    estado         text DEFAULT 'nueva'
                   CHECK (estado IN ('nueva','contactado','en_proceso','cerrado','descartado')),
    asignado_a     uuid REFERENCES miembros(id),
    notas_internas text,
    created_at     timestamptz DEFAULT now(),
    updated_at     timestamptz DEFAULT now()
);
CREATE INDEX idx_sigs_estado ON sigs_solicitudes(estado);

-- Trigger updated_at para las nuevas tablas
CREATE TRIGGER trg_pedidos_updated
    BEFORE UPDATE ON pedidos_productos
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TRIGGER trg_sigs_updated
    BEFORE UPDATE ON sigs_solicitudes
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- Insertar precios de SIGS en configuracion
INSERT INTO configuracion (tenant_id, clave, valor) VALUES
    ('panama', 'precio_sigs_basico',     '300'),
    ('panama', 'precio_sigs_estandar',   '500'),
    ('panama', 'precio_sigs_empresarial','1200'),
    ('panama', 'precio_planton',         '2'),
    ('panama', 'stock_plantones',        '10000')
ON CONFLICT DO NOTHING;
