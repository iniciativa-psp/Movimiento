# PSP v2 — Guía de Instalación y Configuración

## Descripción

El **suite PSP v2** reemplaza los plugins `psp-core`, `psp-auth`, etc. con versiones limpias (`psp-core2`, `psp-auth2`…) que **no dependen de Supabase Auth ni de la cookie `psp_jwt`**. Toda la autenticación es WordPress nativa; el ID de miembro se vincula mediante `wp_user_id` en la tabla `miembros`.

Los plugins v1 existentes **no se tocan** y permanecen activos sin interferencia.

---

## Plugins incluidos

| Plugin | Slug | Descripción | Estado |
|---|---|---|---|
| PSP Core 2 | `psp-core2` | Cliente Supabase, ajustes, helpers, REST `psp/v2` | **MVP completo** |
| PSP Auth 2 | `psp-auth2` | Login/registro/perfil (shortcodes), auto-login | **MVP completo** |
| PSP Territorial 2 | `psp-territorial2` | Selector territorial encadenado (JSON URL o REST PSP Territorial V2) | **MVP completo** |
| PSP Payments 2 | `psp-payments2` | Registro de intent de pago → `pendiente_verificacion` | **MVP completo** |
| PSP Referidos 2 | `psp-referidos2` | Tabla de referidos, enlace propio | **Stub funcional** |
| PSP Ranking 2 | `psp-ranking2` | Tabla líderes, posición propia (read-only) | **Stub funcional** |
| PSP WhatsApp 2 | `psp-whatsapp2` | Grupos WhatsApp por territorio (read-only) | **Stub funcional** |
| PSP Dashboard 2 | `psp-dashboard2` | KPIs públicos + contador regresivo | **Stub funcional** |

---

## Orden de activación

1. **PSP Core 2** — debe activarse primero (define `PSP2_Supabase`, constantes y menú).
2. **PSP Territorial 2** — independiente, puede activarse en cualquier orden.
3. **PSP Auth 2** — depende de PSP Core 2.
4. **PSP Payments 2**, **PSP Referidos 2**, **PSP Ranking 2**, **PSP WhatsApp 2**, **PSP Dashboard 2** — degradan con aviso si Core 2 no está activo.

---

## Configuración de PSP Core 2

Ve a **WP Admin → PSP v2 → Configuración** y completa:

| Campo | Descripción |
|---|---|
| Supabase URL | `https://xxxx.supabase.co` |
| Anon Key | Clave pública (expuesta en JS) |
| Service Role Key | Clave privada (solo servidor, no se expone) |
| Tenant ID | `panama` (o el identificador de tu tenant) |
| Inicio / Fin de Campaña | Fechas ISO 8601 (UTC) |
| Cuota de Membresía | Monto en USD (default: `1.00`) |
| Fecha Lanzamiento | Para el contador regresivo |
| Meta Miembros / Meta Monto | Objetivos de campaña |

---

## Configuración de PSP Territorial 2

Ve a **WP Admin → PSP v2 → Territorial 2** y configura:

- **Modo de carga** — elige entre:
  - **JSON externo (URL)**: carga territorios desde un archivo JSON público. Requiere configurar la URL del JSON.
  - **PSP Territorial V2 (REST local)**: consulta los endpoints REST del plugin externo [PSP Territorial V2](https://github.com/iniciativa-psp/Territorial) instalado en el mismo WordPress. **No requiere URL JSON.** Si ese plugin no está instalado, los selectores mostrarán un mensaje de contacto.
  - **Inline (JS directo)**: reservado para uso futuro.

- **URL del JSON territorial** (solo en modo JSON externo): URL pública que devuelva un array de objetos:
  ```json
  [
    { "id": "1", "nombre": "Panamá", "tipo": "provincia", "parent_id": "" },
    { "id": "10", "nombre": "Panamá", "tipo": "distrito", "parent_id": "1" },
    { "id": "100", "nombre": "Bethania", "tipo": "corregimiento", "parent_id": "10" }
  ]
  ```
  Los `tipo` aceptados son: `provincia`, `distrito`, `corregimiento`, `comunidad`.

### Integración con PSP Territorial V2 (modo `pspv2_rest`)

Cuando el modo es **PSP Territorial V2 (REST local)**, el plugin `psp-territorial2` consulta internamente los endpoints REST expuestos por el plugin externo:

| Endpoint | Descripción |
|---|---|
| `GET /wp-json/psp-territorial/v2/provincias` | Lista de provincias |
| `GET /wp-json/psp-territorial/v2/distritos?parent_id={id}` | Distritos de una provincia |
| `GET /wp-json/psp-territorial/v2/corregimientos?parent_id={id}` | Corregimientos de un distrito |
| `GET /wp-json/psp-territorial/v2/comunidades?parent_id={id}` | Comunidades de un corregimiento |

Los resultados se cachean con **transients** de WordPress (1 hora) para reducir llamadas REST repetidas.

**Fallback:** Si `PSP Territorial V2` no está instalado, el selector mostrará un mensaje invitando al usuario a escribir a `admin@panamasinpobreza.org` para solicitar que el territorio sea agregado.

---

## Configuración de PSP Auth 2

Ve a **WP Admin → PSP v2 → Auth 2** y configura:

| Campo | Descripción |
|---|---|
| URL Política de Privacidad | URL de la página de política de privacidad. Se muestra como enlace en el pie del formulario de registro. Por defecto: `/privacidad/`. Deja en blanco para ocultar el enlace. |

La opción se guarda en `psp2_privacy_url`. También puede establecerse con `update_option('psp2_privacy_url', 'https://...')`.

---

## Páginas requeridas en WordPress

Crea las siguientes páginas con los shortcodes indicados:

| Página | Slug sugerido | Shortcode |
|---|---|---|
| Iniciar sesión | `/login/` | `[psp2_login]` |
| Registro | `/registro/` | `[psp2_registro_completo]` |
| Mi cuenta / Perfil | `/mi-cuenta/` | `[psp2_perfil]` |
| Pagar membresía | `/pago/` | `[psp2_pago]` |
| Mis referidos | `/mis-referidos/` | `[psp2_mis_referidos]` |
| Ranking | `/ranking/` | `[psp2_ranking_nacional]` |
| Grupos WhatsApp | `/grupos/` | `[psp2_wa_grupos]` |
| Dashboard | `/inicio/` | `[psp2_kpis]` y `[psp2_countdown]` |

> **Nota:** el shortcode `[psp2_registro_completo]` incluye automáticamente `[psp2_territorial_selector]` si PSP Territorial 2 está activo.

---

## Migración Supabase requerida

Antes de activar los plugins v2, ejecuta la siguiente migración en Supabase para agregar el campo `wp_user_id` a la tabla `miembros`:

```sql
-- Agregar columna wp_user_id a miembros
ALTER TABLE miembros
  ADD COLUMN IF NOT EXISTS wp_user_id BIGINT;

-- Índice único para garantizar 1 usuario WP ↔ 1 miembro
CREATE UNIQUE INDEX IF NOT EXISTS miembros_wp_user_id_unique
  ON miembros (wp_user_id)
  WHERE wp_user_id IS NOT NULL;
```

> **Importante:** `wp_user_id` almacena el `ID` del usuario de WordPress (tabla `wp_users`). No es el UUID de Supabase Auth.

---

## REST API psp/v2

Todos los endpoints están en `/wp-json/psp/v2/`.

### Endpoints públicos

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/wp-json/psp/v2/kpis` | KPIs de campaña (miembros activos, metas, fechas) |

### Endpoints protegidos (requieren sesión WP + `X-WP-Nonce`)

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/wp-json/psp/v2/me` | Perfil del miembro autenticado |
| `GET` | `/wp-json/psp/v2/wa-group` | Grupos WhatsApp asignados |
| `POST` | `/wp-json/psp/v2/pago-intent` | Registrar intent de pago |

Para usar los endpoints protegidos desde JS, incluir la cabecera `X-WP-Nonce` con el valor de `PSP2_CONFIG.rest_nonce`.

---

## Flujo de registro

1. Usuario accede a `/registro/` → shortcode `[psp2_registro_completo]`.
2. Llena nombre, teléfono, contraseña, **tipo de miembro** (`persona`, `empresa`, `organizacion`, `entidad_publica`) y **territorio** (Panamá o Internacional).
3. Al enviar: `wp_ajax_psp2_register` crea usuario WP (`user_login` = teléfono) y fila en `miembros` (`estado: pendiente_pago`).
4. Auto-login inmediato → redirección a `/mi-cuenta/`.
5. Desde `/mi-cuenta/` el miembro ve el botón "Activar membresía" → `/pago/`.
6. Registra pago → estado `pendiente_verificacion` en tabla `pagos`.
7. Admin o webhook verifica y cambia estado a `activo`.

---

## Captura de referidos

El código de referido se captura desde el parámetro `?ref=CODIGO` en la URL. Se guarda en la cookie `psp2_ref` (30 días, `HttpOnly`, `Secure`). Al registrarse, se resuelve el `referidor_id` en `miembros` y se guarda `referido_por` en la nueva fila.

---

## Estado del sistema

Ve a **WP Admin → PSP v2 → Estado** para ver:
- Estado de activación de cada plugin v2.
- Verificación de opciones de configuración.
- Botón para probar la conexión con Supabase.

---

## MVP completo vs. Stubbed

### ✅ MVP completo
- Registro + auto-login WP.
- Creación de fila en `miembros` con `wp_user_id` y atribución de referido.
- Shortcodes de login, registro completo y perfil.
- Registro de intent de pago (`pendiente_verificacion`).
- Endpoints REST `kpis`, `me`, `wa-group`, `pago-intent`.
- Selector territorial encadenado vía JSON o REST (PSP Territorial V2).
- Mensaje de contacto cuando un nivel territorial no tiene datos.
- Panel admin con settings y test de conexión.

### 🔧 Stubbed (funcional pero sin lógica de negocio avanzada)
- Confirmación automática de pagos vía webhook (manual por ahora).
- Cálculo de ranking (depende de vista/función Supabase).
- Logs de referidos (depende de trigger en `miembros`).
- Notificaciones push/WhatsApp al registro.
