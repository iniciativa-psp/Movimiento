-- ============================================================
-- PANAMÁ SIN POBREZA — Schema completo PostgreSQL (Supabase)
-- Versión: 1.0.0 | Producción
-- ============================================================

-- Extensiones
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";
CREATE EXTENSION IF NOT EXISTS "unaccent";

-- ────────────────────────────────────────────────────────────
-- TABLA: tenants (Multi-país SaaS)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tenants (
  id          text PRIMARY KEY DEFAULT 'panama',
  nombre      text NOT NULL,
  pais        text NOT NULL DEFAULT 'PA',
  dominio     text,
  moneda      text DEFAULT 'USD',
  activo      boolean DEFAULT true,
  config      jsonb DEFAULT '{}',
  created_at  timestamptz DEFAULT now()
);
INSERT INTO tenants (id, nombre, pais, dominio) VALUES ('panama','Panamá Sin Pobreza','PA','panamasinpobreza.org')
  ON CONFLICT DO NOTHING;

-- ────────────────────────────────────────────────────────────
-- TABLA: territorios
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS territorios (
  id          uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id   text REFERENCES tenants(id) DEFAULT 'panama',
  tipo        text NOT NULL CHECK (tipo IN ('pais','provincia','distrito','corregimiento','comunidad','ciudad')),
  nombre      text NOT NULL,
  codigo      text,
  parent_id   uuid REFERENCES territorios(id),
  lat         numeric(10,7),
  lng         numeric(10,7),
  activo      boolean DEFAULT true,
  created_at  timestamptz DEFAULT now()
);
CREATE INDEX idx_territorios_parent ON territorios(parent_id);
CREATE INDEX idx_territorios_tipo   ON territorios(tipo);
CREATE INDEX idx_territorios_nombre ON territorios USING gin(nombre gin_trgm_ops);

-- ────────────────────────────────────────────────────────────
-- TABLA: territorios_solicitudes
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS territorios_solicitudes (
  id            uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  solicitante_id uuid,
  tipo          text,
  nombre        text,
  parent_id     uuid REFERENCES territorios(id),
  estado        text DEFAULT 'pendiente' CHECK (estado IN ('pendiente','aprobado','rechazado')),
  revisado_por  uuid,
  nota          text,
  created_at    timestamptz DEFAULT now()
);

-- ────────────────────────────────────────────────────────────
-- TABLA: miembros
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS miembros (
  id                    uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id               uuid REFERENCES auth.users(id) ON DELETE SET NULL,
  tenant_id             text REFERENCES tenants(id) DEFAULT 'panama',
  nombre                text NOT NULL,
  celular               text UNIQUE,
  email                 text,
  tipo_miembro          text DEFAULT 'nacional' CHECK (tipo_miembro IN (
                          'nacional','internacional','actor','sector',
                          'hogar_solidario','productor','planton','comunicador',
                          'influencer','embajador','lider','voluntario','coordinador')),
  estado                text DEFAULT 'pendiente_pago' CHECK (estado IN (
                          'pendiente_pago','activo','inactivo','suspendido','baja')),
  codigo_referido_propio text UNIQUE NOT NULL,
  referido_por          uuid REFERENCES miembros(id),
  puntos_total          integer DEFAULT 0,
  nivel                 text DEFAULT 'Simpatizante',
  provincia_id          uuid REFERENCES territorios(id),
  distrito_id           uuid REFERENCES territorios(id),
  corregimiento_id      uuid REFERENCES territorios(id),
  comunidad_id          uuid REFERENCES territorios(id),
  pais_id               text DEFAULT 'PA',
  ciudad                text,
  ocupacion             text,
  sector                text,
  avatar_url            text,
  bio                   text,
  redes_sociales        jsonb DEFAULT '{}',
  config                jsonb DEFAULT '{}',
  ip_registro           inet,
  ultimo_login          timestamptz,
  created_at            timestamptz DEFAULT now(),
  updated_at            timestamptz DEFAULT now()
);
CREATE INDEX idx_miembros_tenant     ON miembros(tenant_id);
CREATE INDEX idx_miembros_codigo     ON miembros(codigo_referido_propio);
CREATE INDEX idx_miembros_referido   ON miembros(referido_por);
CREATE INDEX idx_miembros_provincia  ON miembros(provincia_id);
CREATE INDEX idx_miembros_estado     ON miembros(estado);
CREATE INDEX idx_miembros_nombre     ON miembros USING gin(nombre gin_trgm_ops);

-- ────────────────────────────────────────────────────────────
-- TABLA: pagos
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pagos (
  id                uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  miembro_id        uuid NOT NULL REFERENCES miembros(id),
  tenant_id         text REFERENCES tenants(id) DEFAULT 'panama',
  monto             numeric(10,2) NOT NULL CHECK (monto > 0),
  metodo            text NOT NULL CHECK (metodo IN (
                      'yappy','clave','tarjeta','ach','puntopago',
                      'transferencia','efectivo','otro')),
  tipo_membresia    text,
  estado            text DEFAULT 'pendiente' CHECK (estado IN (
                      'pendiente','pendiente_validacion','completado','fallido','reembolsado','cancelado')),
  referencia        text UNIQUE,
  transaction_id    text,
  provider_response jsonb,
  comprobante_url   text,
  factura_id        uuid,
  validado_por      uuid,
  nota_admin        text,
  created_at        timestamptz DEFAULT now(),
  updated_at        timestamptz DEFAULT now()
);
CREATE INDEX idx_pagos_miembro   ON pagos(miembro_id);
CREATE INDEX idx_pagos_estado    ON pagos(estado);
CREATE INDEX idx_pagos_referencia ON pagos(referencia);
CREATE INDEX idx_pagos_created   ON pagos(created_at DESC);

-- ────────────────────────────────────────────────────────────
-- TABLA: referidos_log
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS referidos_log (
  id              uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  referidor_id    uuid NOT NULL REFERENCES miembros(id),
  referido_id     uuid NOT NULL REFERENCES miembros(id),
  pago_id         uuid REFERENCES pagos(id),
  puntos_ganados  integer DEFAULT 0,
  nivel_cadena    integer DEFAULT 1,
  created_at      timestamptz DEFAULT now()
);
CREATE INDEX idx_refs_referidor ON referidos_log(referidor_id);

-- ────────────────────────────────────────────────────────────
-- TABLA: puntos_historial
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS puntos_historial (
  id          uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  miembro_id  uuid NOT NULL REFERENCES miembros(id),
  puntos      integer NOT NULL,
  tipo        text NOT NULL,
  referencia  text,
  descripcion text,
  created_at  timestamptz DEFAULT now()
);
CREATE INDEX idx_puntos_miembro ON puntos_historial(miembro_id);

-- ────────────────────────────────────────────────────────────
-- TABLA: niveles
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS niveles (
  id          serial PRIMARY KEY,
  nombre      text NOT NULL,
  icono       text,
  min_puntos  integer NOT NULL,
  max_puntos  integer,
  beneficios  jsonb DEFAULT '[]',
  color       text
);
INSERT INTO niveles (nombre, icono, min_puntos, max_puntos, color) VALUES
  ('Simpatizante', '🌱', 0,      499,   '#9FE1CB'),
  ('Promotor',     '⭐', 500,   1999,   '#EF9F27'),
  ('Embajador',    '🌟', 2000,  4999,   '#0C447C'),
  ('Líder',        '💫', 5000,  9999,   '#0B5E43'),
  ('Champion',     '🏆', 10000, NULL,   '#C9381A')
ON CONFLICT DO NOTHING;

-- ────────────────────────────────────────────────────────────
-- TABLA: insignias
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS insignias (
  id          uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  nombre      text NOT NULL,
  descripcion text,
  icono       text,
  condicion   jsonb,
  puntos_bonus integer DEFAULT 0,
  activa      boolean DEFAULT true
);

-- ────────────────────────────────────────────────────────────
-- TABLA: miembros_insignias
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS miembros_insignias (
  miembro_id  uuid REFERENCES miembros(id),
  insignia_id uuid REFERENCES insignias(id),
  obtenida_at timestamptz DEFAULT now(),
  PRIMARY KEY (miembro_id, insignia_id)
);

-- ────────────────────────────────────────────────────────────
-- TABLA: retos
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS retos (
  id           uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id    text REFERENCES tenants(id),
  titulo       text NOT NULL,
  descripcion  text,
  tipo         text CHECK (tipo IN ('individual','equipo','territorial','global')),
  periodo      text CHECK (periodo IN ('diario','semanal','mensual','unico')),
  meta_valor   numeric,
  meta_tipo    text,
  puntos_premio integer DEFAULT 0,
  fecha_inicio  date,
  fecha_fin     date,
  activo       boolean DEFAULT true,
  created_at   timestamptz DEFAULT now()
);

-- ────────────────────────────────────────────────────────────
-- TABLA: retos_participacion
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS retos_participacion (
  id          uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  reto_id     uuid REFERENCES retos(id),
  miembro_id  uuid REFERENCES miembros(id),
  progreso    numeric DEFAULT 0,
  completado  boolean DEFAULT false,
  puntos_obtenidos integer DEFAULT 0,
  evidencia_url text,
  created_at  timestamptz DEFAULT now(),
  UNIQUE(reto_id, miembro_id)
);

-- ────────────────────────────────────────────────────────────
-- TABLA: ranking (snapshot diario)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ranking (
  id          uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id   text REFERENCES tenants(id) DEFAULT 'panama',
  tipo        text NOT NULL,
  entidad_id  text NOT NULL,
  nombre      text NOT NULL,
  total       integer DEFAULT 0,
  monto_total numeric(12,2) DEFAULT 0,
  posicion    integer,
  fecha       date DEFAULT CURRENT_DATE,
  created_at  timestamptz DEFAULT now()
);
CREATE INDEX idx_ranking_fecha ON ranking(fecha DESC);
CREATE INDEX idx_ranking_tipo  ON ranking(tipo, total DESC);

-- ────────────────────────────────────────────────────────────
-- TABLA: actores / coaliciones / patrocinadores
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS actores (
  id              uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id       text REFERENCES tenants(id) DEFAULT 'panama',
  tipo            text CHECK (tipo IN ('coalicion','patrocinador','comunicador','influencer','embajador','voluntario')),
  nombre          text NOT NULL,
  descripcion     text,
  logo_url        text,
  website         text,
  miembro_id      uuid REFERENCES miembros(id),
  codigo_actor    text UNIQUE,
  estado          text DEFAULT 'activo',
  provincias      uuid[],
  config          jsonb DEFAULT '{}',
  created_at      timestamptz DEFAULT now()
);

-- ────────────────────────────────────────────────────────────
-- TABLA: colectivos / sectores
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS colectivos (
  id          uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id   text REFERENCES tenants(id) DEFAULT 'panama',
  nombre      text NOT NULL,
  tipo        text CHECK (tipo IN ('colectivo','sector','facultad','grupo','empresa')),
  descripcion text,
  codigo      text UNIQUE,
  lider_id    uuid REFERENCES miembros(id),
  territorio_id uuid REFERENCES territorios(id),
  miembros_count integer DEFAULT 0,
  activo      boolean DEFAULT true,
  created_at  timestamptz DEFAULT now()
);

-- ────────────────────────────────────────────────────────────
-- TABLA: whatsapp_grupos
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS whatsapp_grupos (
  id              uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id       text REFERENCES tenants(id) DEFAULT 'panama',
  nombre          text NOT NULL,
  link            text,
  tipo            text CHECK (tipo IN ('territorial','sector','actor','embajador','general')),
  territorio_id   uuid REFERENCES territorios(id),
  actor_id        uuid REFERENCES actores(id),
  miembros_max    integer DEFAULT 256,
  miembros_actual integer DEFAULT 0,
  admin_id        uuid REFERENCES miembros(id),
  activo          boolean DEFAULT true,
  created_at      timestamptz DEFAULT now()
);

-- ────────────────────────────────────────────────────────────
-- TABLA: erp_clientes
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS erp_clientes (
  id          uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id   text REFERENCES tenants(id) DEFAULT 'panama',
  miembro_id  uuid REFERENCES miembros(id),
  tipo        text DEFAULT 'persona_natural',
  nombre      text NOT NULL,
  ruc         text,
  dv          text,
  email       text,
  telefono    text,
  direccion   text,
  created_at  timestamptz DEFAULT now()
);

-- ────────────────────────────────────────────────────────────
-- TABLA: facturas
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS facturas (
  id                uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id         text REFERENCES tenants(id) DEFAULT 'panama',
  pago_id           uuid REFERENCES pagos(id),
  cliente_id        uuid REFERENCES erp_clientes(id),
  numero_factura    text UNIQUE,
  tipo              text DEFAULT 'electronica',
  estado            text DEFAULT 'pendiente' CHECK (estado IN ('pendiente','emitida','enviada_pac','aceptada_dgi','rechazada','anulada')),
  subtotal          numeric(10,2),
  itbms             numeric(10,2) DEFAULT 0,
  total             numeric(10,2),
  xml_content       text,
  pac_respuesta     jsonb,
  dgi_cufe          text,
  fecha_emision     timestamptz DEFAULT now(),
  fecha_vencimiento date,
  concepto          text,
  created_at        timestamptz DEFAULT now()
);
CREATE INDEX idx_facturas_pago ON facturas(pago_id);

-- ────────────────────────────────────────────────────────────
-- TABLA: erp_transacciones
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS erp_transacciones (
  id              uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id       text REFERENCES tenants(id) DEFAULT 'panama',
  tipo            text CHECK (tipo IN ('ingreso','egreso','transferencia')),
  categoria       text,
  descripcion     text NOT NULL,
  monto           numeric(10,2) NOT NULL,
  pago_id         uuid REFERENCES pagos(id),
  factura_id      uuid REFERENCES facturas(id),
  cuenta_debito   text,
  cuenta_credito  text,
  referencia      text,
  fecha           date DEFAULT CURRENT_DATE,
  created_at      timestamptz DEFAULT now()
);

-- ────────────────────────────────────────────────────────────
-- TABLA: erp_libro_diario
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS erp_libro_diario (
  id              uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id       text REFERENCES tenants(id) DEFAULT 'panama',
  transaccion_id  uuid REFERENCES erp_transacciones(id),
  fecha           date DEFAULT CURRENT_DATE,
  descripcion     text,
  cuenta          text NOT NULL,
  debe            numeric(10,2) DEFAULT 0,
  haber           numeric(10,2) DEFAULT 0,
  created_at      timestamptz DEFAULT now()
);

-- ────────────────────────────────────────────────────────────
-- TABLA: webhooks_logs
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS webhooks_logs (
  id          uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  proveedor   text,
  payload     text,
  signature   text,
  ip          inet,
  estado      text DEFAULT 'recibido',
  error       text,
  created_at  timestamptz DEFAULT now()
);
CREATE INDEX idx_webhooks_created ON webhooks_logs(created_at DESC);

-- ────────────────────────────────────────────────────────────
-- TABLA: auditoria
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS auditoria (
  id          uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  miembro_id  uuid REFERENCES miembros(id),
  accion      text NOT NULL,
  datos       jsonb,
  ip          inet,
  user_agent  text,
  created_at  timestamptz DEFAULT now()
);
CREATE INDEX idx_auditoria_miembro ON auditoria(miembro_id);
CREATE INDEX idx_auditoria_accion  ON auditoria(accion);
CREATE INDEX idx_auditoria_created ON auditoria(created_at DESC);

-- ────────────────────────────────────────────────────────────
-- TABLA: configuracion (multi-tenant)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS configuracion (
  tenant_id   text REFERENCES tenants(id),
  clave       text NOT NULL,
  valor       text,
  descripcion text,
  tipo        text DEFAULT 'string',
  PRIMARY KEY (tenant_id, clave)
);
INSERT INTO configuracion (tenant_id, clave, valor) VALUES
  ('panama', 'meta_miembros',   '1000000'),
  ('panama', 'meta_monto',      '1000000'),
  ('panama', 'launch_date',     '2026-05-12'),
  ('panama', 'precio_nacional', '5'),
  ('panama', 'precio_inter',    '10'),
  ('panama', 'precio_actor',    '25'),
  ('panama', 'precio_sector',   '50'),
  ('panama', 'precio_hogar',    '15'),
  ('panama', 'precio_productor','20'),
  ('panama', 'precio_planton',  '2'),
  ('panama', 'yappy_numero',    ''),
  ('panama', 'yappy_nombre',    'Panamá Sin Pobreza'),
  ('panama', 'itbms_rate',      '0'),
  ('panama', 'pac_url',         ''),
  ('panama', 'pac_token',       '')
ON CONFLICT DO NOTHING;


-- ── Notificaciones (agregado en revisión) ────────────────────────────────────
CREATE TABLE IF NOT EXISTS notificaciones (
  id          uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id   text REFERENCES tenants(id) DEFAULT 'panama',
  miembro_id  uuid REFERENCES miembros(id),
  tipo        text NOT NULL,
  mensaje     text NOT NULL,
  datos       jsonb DEFAULT '{}',
  leida       boolean DEFAULT false,
  created_at  timestamptz DEFAULT now()
);
CREATE INDEX idx_notif_miembro ON notificaciones(miembro_id);
CREATE INDEX idx_notif_leida   ON notificaciones(leida, created_at DESC);
ALTER TABLE notificaciones ENABLE ROW LEVEL SECURITY;
CREATE POLICY "notif_own" ON notificaciones FOR SELECT
  USING (miembro_id IN (SELECT id FROM miembros WHERE user_id=auth.uid()) OR auth.role()='service_role');

-- ── Push subscriptions ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS push_subscriptions (
  id          uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id   text REFERENCES tenants(id) DEFAULT 'panama',
  miembro_id  uuid REFERENCES miembros(id),
  subscription text NOT NULL,
  activa      boolean DEFAULT true,
  created_at  timestamptz DEFAULT now()
);

-- ── Pedidos productos (si no se ejecutó 03) ───────────────────────────────────
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
  estado           text DEFAULT 'pendiente_pago',
  notas            text,
  created_at       timestamptz DEFAULT now(),
  updated_at       timestamptz DEFAULT now()
);

-- ── SIGS solicitudes (si no se ejecutó 03) ───────────────────────────────────
CREATE TABLE IF NOT EXISTS sigs_solicitudes (
  id             uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id      text REFERENCES tenants(id) DEFAULT 'panama',
  nombre         text NOT NULL,
  email          text NOT NULL,
  celular        text,
  organizacion   text,
  plan           text DEFAULT 'estandar',
  descripcion    text,
  estado         text DEFAULT 'nueva',
  notas_internas text,
  created_at     timestamptz DEFAULT now(),
  updated_at     timestamptz DEFAULT now()
);
