# Movimiento Panamá Sin Pobreza — Sistema de Plugins WordPress + Supabase

> **Campaña activa:** 14 de abril 2026 → 18 de mayo 2026  
> **Meta:** 1 millón de miembros · B/.1 millón recaudados en 30 días

## Descripción

Plataforma plugin-first sobre WordPress integrada con Supabase para el **Movimiento Panamá Sin Pobreza**. Permite registro viral, membresías, gamificación, referidos, pagos multi-método, grupos de WhatsApp territoriales y rankings en tiempo real.

**Dominios:**
- `panamasinpobreza.org` — Landing pública y PWA
- `sistema.panamasinpobreza.org` — WordPress admin y API

---

## Estructura de plugins

| Plugin | Propósito |
|--------|-----------|
| `psp-core` | Núcleo: Supabase client, helpers, REST API (`/wp-json/psp/v1/`), config campaña |
| `psp-auth` | Auth (magic link + OTP), registro + pago B/.1, shortcodes `[psp_registro_completo]` |
| `psp-territorial` | Selector encadenado Provincia→Distrito→Corregimiento→Comunidad (integra con PSP Territorial V2) |
| `psp-payments` | Yappy, Clave (PagueloFacil), Tarjeta BG, PuntoPago, PayPal, Transferencias, Efectivo |
| `psp-referidos` | Códigos únicos, árbol de referidos, shortcode `[psp_mi_referido]` con botón WhatsApp |
| `psp-whatsapp` | Grupos WA por territorio/sector, shortcodes `[psp_whatsapp_grupos]` y `[psp_mi_grupo_wa]` |
| `psp-ranking` | Ranking global/provincial/país, shortcode `[psp_ranking]` y `[psp_mi_posicion]` |
| `psp-dashboard` | KPIs en tiempo real, termómetro, mapa, countdown, shortcode `[psp_dashboard_publico]` |
| `psp-membresias` | Tipos y precios de membresías configurables |
| `psp-productos` | Catálogo de productos (plantones, etc.) |
| `psp-notificaciones` | Emails, push web, WhatsApp |
| `psp-pwa` | Manifest, service worker, PWA instálable |
| `psp-facturacion` | Facturación electrónica DGI |
| `psp-erp` | Contabilidad interna |

---

## Instalación rápida

### Requisitos
- WordPress ≥ 6.4 + PHP ≥ 8.0
- Proyecto Supabase activo (Free tier es suficiente para MVP)
- SSL en ambos dominios

### 1. Clonar e instalar plugins

```bash
# Desde el directorio de plugins de WordPress
cp -r plugins/* /var/www/html/wp-content/plugins/
```

O usando WP-CLI:

```bash
wp plugin activate psp-core psp-auth psp-territorial psp-payments \
  psp-referidos psp-whatsapp psp-ranking psp-dashboard psp-membresias \
  psp-productos psp-notificaciones psp-pwa
```

### 2. Ejecutar migraciones SQL en Supabase

En el **SQL Editor** de tu proyecto Supabase, ejecuta en orden:

```
sql/01_schema_completo.sql   ← Schema base (tablas, índices, RLS)
sql/02_functions_triggers.sql ← Funciones y triggers
sql/03_membresias_productos.sql ← Tablas adicionales
sql/04_campaign_mvp.sql       ← Campaña abr-may 2026, anti-fraude, vistas materializadas
```

### 3. Configurar variables de entorno

Ir a **WordPress Admin → PSP Sistema → Configuración Central** y completar:

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| Supabase URL | URL de tu proyecto | `https://abcxyz.supabase.co` |
| Anon Key | Clave pública (anon) de Supabase | `eyJhbGc...` |
| Service Role Key | Clave privada (nunca en frontend) | `eyJhbGc...` |
| Tenant ID | Identificador del tenant | `panama` |
| Inicio de Campaña | Fecha/hora inicio (UTC) | `2026-04-14T00:00:00` |
| Fin de Campaña | Fecha/hora cierre (UTC) | `2026-05-18T23:59:59` |
| Cuota de Membresía | Monto mínimo en B/. | `1.00` |
| Meta Miembros | Objetivo de miembros | `1000000` |
| Meta Recaudación | Objetivo en B/. | `1000000` |

### 4. Configurar métodos de pago

En **WordPress Admin → PSP Sistema → Pagos** o directamente en las opciones de WP:

```bash
wp option set psp_yappy_numero "+50761234567"
wp option set psp_yappy_nombre "Panama Sin Pobreza"
wp option set psp_banco_cuenta "12345678"
wp option set psp_banco_nombre "Banco General"
wp option set psp_banco_titular "Iniciativa Panama Sin Pobreza"
wp option set psp_paypal_email "pagos@panamasinpobreza.org"
```

### 5. Configurar integración con PSP Territorial V2

En **WordPress Admin → PSP Sistema → Territorial**, configurar:
- **URL JSON externo** → URL del endpoint REST del plugin `PSP Territorial V2` (ej: `https://sistema.panamasinpobreza.org/wp-admin/admin.php?page=psp-territorial&action=json`)
- O **Ruta JSON local** si tienes acceso al archivo JSON de territorios

### 6. Crear páginas WordPress

```
/registro/  → [psp_registro_completo]
/mi-cuenta/ → [psp_perfil]
/grupos/    → [psp_mi_grupo_wa]
             [psp_whatsapp_grupos]
/ranking/   → [psp_ranking tipo="provincia" limite="20"]
/inicio/    → [psp_termometro]
             [psp_dashboard_publico]
             [psp_countdown]
```

---

## REST API (`/wp-json/psp/v1/`)

Todos los endpoints autenticados requieren el JWT de Supabase en el header:
```
Authorization: Bearer <supabase_access_token>
```

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| `GET`  | `/me` | ✅ JWT | Perfil del miembro + referral link + ranking |
| `GET`  | `/wa-group` | ✅ JWT | Grupos de WhatsApp asignados al miembro por territorio |
| `POST` | `/registro` | ❌ Público | Registrar nuevo miembro (para PWA) |
| `POST` | `/pago-confirmar` | ✅ JWT | Registrar pago de membresía |
| `GET`  | `/kpis` | ❌ Público | KPIs de la campaña |

### Ejemplo: Registro vía REST (PWA)

```bash
curl -X POST https://sistema.panamasinpobreza.org/wp-json/psp/v1/registro \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Juan Pérez",
    "celular": "+50761234567",
    "email": "juan@ejemplo.com",
    "provincia_id": "uuid-de-panama",
    "ref": "PSP-AB12CD34"
  }'
```

### Ejemplo: Ver grupo de WhatsApp asignado

```bash
curl -X GET https://sistema.panamasinpobreza.org/wp-json/psp/v1/wa-group \
  -H "Authorization: Bearer eyJhbGc..."
```

---

## Flujo de Registro MVP

```
Usuario llega a /registro/?ref=PSP-XXXXXXXX
        ↓
[psp_registro_completo] captura ?ref= en cookie
        ↓
Paso 1: Nombre + Celular + Email + Territorio
    → POST /wp-admin/admin-ajax.php?action=psp_registro
    → Crea miembro con estado=pendiente_pago
        ↓
Paso 2: Selecciona método de pago (B/.1)
    → Muestra instrucciones (Yappy QR/número, transferencia, etc.)
    → POST ?action=psp_registrar_pago_membresia
    → Registra pago en Supabase (estado=pendiente_verificacion para manuales)
        ↓
Paso 3: Pantalla de éxito
    → Muestra enlace de referido personal
    → Botón "Compartir por WhatsApp" (mensaje pre-formateado)
    → Link "Ver mi cuenta"
```

---

## Viralidad y Referidos

- Cada miembro recibe un código único (`PSP-XXXXXXXX`)
- URL de referido: `https://panamasinpobreza.org/?ref=PSP-XXXXXXXX`
- El parámetro `?ref=` se persiste en cookie por 30 días
- Al registrarse con el código de otra persona, se registra en `referidos_log` y se suman puntos
- Shortcode `[psp_mi_referido]` muestra el link personal con botones de compartir (WhatsApp, Telegram, Facebook, Twitter)

---

## Grupos de WhatsApp

- Administrados desde **WP Admin → PSP Sistema → WhatsApp**
- Se pueden asignar por **territorio** (provincia/distrito/corregimiento/comunidad), **sector** o **embajador**
- El shortcode `[psp_mi_grupo_wa]` muestra automáticamente el grupo más específico para el territorio del miembro
- La API REST `/wp-json/psp/v1/wa-group` expone los grupos para la PWA
- Restricción anti-fraude: links de grupos únicos (no se puede registrar el mismo link dos veces)

---

## Seguridad

- **Service Role Key** solo se usa en PHP server-side (nunca se expone al browser)
- **Anon Key** es pública y va al frontend para operaciones de lectura
- Nonces de WordPress en todas las peticiones AJAX
- Row Level Security (RLS) habilitado en todas las tablas críticas
- Rate limiting en endpoints de registro y OTP
- Constraints UNIQUE en email, celular y links de grupos (anti-fraude)

---

## Supabase Edge Functions

```
supabase/functions/crear-pago/       ← Crear intención de pago
supabase/functions/factura-generar/  ← Generar PDF de factura
supabase/functions/ranking-update/   ← Actualizar vista materializada de ranking
supabase/functions/webhook-pago/     ← Recibir webhooks de pasarelas de pago
```

Para desplegar:
```bash
supabase functions deploy crear-pago
supabase functions deploy factura-generar
supabase functions deploy ranking-update
supabase functions deploy webhook-pago
```

---

## Variables de entorno (`.env` o WP options)

```env
# Supabase
PSP_SUPABASE_URL=https://tu-proyecto.supabase.co
PSP_SUPABASE_ANON_KEY=eyJhbGciOiJIUzI1NiIs...
PSP_SUPABASE_SERVICE_KEY=eyJhbGciOiJIUzI1NiIs...   # SOLO servidor

# Campaña
PSP_CAMPAIGN_START=2026-04-14T00:00:00
PSP_CAMPAIGN_END=2026-05-18T23:59:59
PSP_MEMBERSHIP_FEE=1.00

# Pagos
PSP_YAPPY_NUMERO=+50761234567
PSP_YAPPY_NOMBRE=Panama Sin Pobreza
PSP_BANCO_CUENTA=12345678
PSP_BANCO_NOMBRE=Banco General
PSP_BANCO_TITULAR=Iniciativa Panama Sin Pobreza
PSP_PAYPAL_EMAIL=pagos@panamasinpobreza.org

# Metas
PSP_META_MIEMBROS=1000000
PSP_META_MONTO=1000000
```

---

## Contribución

Ver [docs/GUIA_COMPLETA.md](docs/GUIA_COMPLETA.md) para documentación técnica detallada.
