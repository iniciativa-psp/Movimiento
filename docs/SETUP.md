# Guía de Configuración — PSP Sistema

## Prerrequisitos

| Componente | Versión mínima | Notas |
|------------|---------------|-------|
| WordPress  | 6.4+          | Con permalink personalizado activado |
| PHP        | 8.0+          | Extensiones: `curl`, `json`, `mbstring` |
| Supabase   | Free tier OK  | PostgreSQL 15+ |
| SSL        | Obligatorio   | Ambos dominios |

---

## Paso 1: Crear proyecto Supabase

1. Ve a [supabase.com](https://supabase.com) → **New project**
2. Nombre sugerido: `psp-movimiento`
3. Región: **South America (São Paulo)** (más cercana a Panamá)
4. Guarda la **Database password** en lugar seguro
5. Copia de **Project Settings → API**:
   - `URL` → `PSP_SUPABASE_URL`
   - `anon public` key → `PSP_SUPABASE_ANON_KEY`
   - `service_role` key → `PSP_SUPABASE_SERVICE_KEY` (**¡nunca en frontend!**)

---

## Paso 2: Ejecutar migraciones SQL

En **Supabase Dashboard → SQL Editor**, ejecuta en este orden:

1. Abre **Supabase Dashboard → SQL Editor → New query**
2. Copia y pega el contenido de cada archivo en el orden indicado, ejecutando cada uno antes del siguiente:

```
1. sql/01_schema_completo.sql    ← Schema base (tablas, índices, RLS)
2. sql/02_functions_triggers.sql ← Funciones y triggers
3. sql/03_membresias_productos.sql ← Tablas adicionales
4. sql/04_campaign_mvp.sql       ← Campaña abr-may 2026, anti-fraude, vistas materializadas
5. sql/05_wp_auth_link.sql       ← Columna wp_user_id + índice único (enlace WP ↔ Supabase)
```

> **Tip:** Haz clic en "Run" después de pegar cada archivo. Espera el mensaje `Success` antes de continuar con el siguiente.

---

## Paso 3: Configurar WordPress Auth

La autenticación utiliza **WordPress nativo** (registro público con usuario y contraseña). No se usa Supabase Auth (sin magic link, sin OTP por correo, sin OTP por SMS).

En **WP Admin → Ajustes → General**:

1. **Membresía** → Activar "Cualquiera puede registrarse" = ✅ ON
2. **Función predeterminada para nuevos usuarios** → `Suscriptor`

En **WP Admin → Ajustes → Correo electrónico** (o con WP-CLI):
```bash
# Desactivar el correo de notificación de nuevo usuario al admin (opcional)
wp option update blogname "Movimiento Panamá Sin Pobreza"
wp option update admin_email "admin@panamasinpobreza.org"
```

Para que los usuarios puedan restablecer su contraseña, WordPress incluye el flujo estándar en `/wp-login.php?action=lostpassword`. Puedes redirigir a esta página desde el perfil.

> **Nota:** El teléfono (celular) es un campo **obligatorio** en el formulario de registro, pero **no se envía ningún SMS**. Solo se almacena para uso interno y comunicaciones coordinadas manualmente.

---

## Paso 4: Instalar y activar plugins WordPress

```bash
# Copiar plugins al directorio de WordPress
cp -r plugins/* /path/to/wordpress/wp-content/plugins/

# Activar con WP-CLI (orden importante: psp-core primero)
wp plugin activate psp-core
wp plugin activate psp-auth psp-territorial psp-payments psp-referidos
wp plugin activate psp-whatsapp psp-ranking psp-dashboard
wp plugin activate psp-membresias psp-productos
wp plugin activate psp-notificaciones psp-pwa psp-facturacion psp-erp
```

---

## Paso 5: Configurar PSP Core

Ve a **WP Admin → PSP Sistema → Configuración Central**:

### Supabase

```
URL:              https://tu-proyecto.supabase.co
Anon Key:         eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
Service Role Key: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
Tenant ID:        panama
```

### Campaña

```
Inicio de Campaña:   2026-04-14T00:00:00
Fin de Campaña:      2026-05-18T23:59:59
Cuota de Membresía:  1.00
Meta Miembros:       1000000
Meta Recaudación:    1000000
```

---

## Paso 6: Configurar métodos de pago

Ve a **WP Admin → PSP Sistema → Pagos** o usa WP-CLI:

### Yappy (prioritario)

```bash
wp option update psp_yappy_numero "+50761234567"
wp option update psp_yappy_nombre "Panama Sin Pobreza"
```

### Banco (ACH/Transferencia Nacional)

```bash
wp option update psp_banco_nombre "Banco General"
wp option update psp_banco_cuenta "123456789012"
wp option update psp_banco_titular "Iniciativa Panama Sin Pobreza"
wp option update psp_banco_ruc "8-123-456"
```

### PayPal

```bash
wp option update psp_paypal_email "pagos@panamasinpobreza.org"
```

### SWIFT (Transferencia Internacional)

```bash
wp option update psp_swift_iban "PA12BOGE00000001234567890"
```

---

## Paso 7: Configurar PSP Territorial

El plugin se integra con **PSP Territorial V2** (`iniciativa-psp/Territorial`).

Ve a **WP Admin → PSP Sistema → Territorial**:

**Opción A — URL JSON externo** (recomendado):
```
https://sistema.panamasinpobreza.org/wp-admin/admin.php?page=psp-territorial&action=json
```

**Opción B — Archivo JSON local**:
```
/path/to/wordpress/wp-content/plugins/psp-territorial-v2/data/territorios.json
```

**Opción C — Supabase** (si migras los territorios a Supabase):
- Seleccionar modo `supabase` en la configuración
- Los territorios deben estar en la tabla `territorios`

---

## Paso 8: Crear páginas WordPress

| URL | Shortcode | Descripción |
|-----|-----------|-------------|
| `/registro/` | `[psp_registro_completo]` | Registro completo + pago B/.1 |
| `/mi-cuenta/` | `[psp_perfil]` | Perfil del miembro |
| `/grupos/` | `[psp_mi_grupo_wa]` `[psp_whatsapp_grupos]` | Grupos de WhatsApp |
| `/ranking/` | `[psp_ranking tipo="provincia" limite="20"]` | Ranking |
| `/` | `[psp_termometro]` `[psp_dashboard_publico]` `[psp_countdown]` | Landing |

---

## Paso 9: Configurar grupos de WhatsApp

1. Ve a **WP Admin → PSP Sistema → WhatsApp**
2. Crea grupos con:
   - **Nombre**: descriptivo (ej: "Grupo Panamá Oeste - Coordinación")
   - **Link**: enlace de invitación (`https://chat.whatsapp.com/...`)
   - **Tipo**: `territorial` | `sector` | `embajador` | `general`
3. Para grupos territoriales, asigna el territorio correspondiente (provincia, distrito, etc.)

---

## Paso 10: Configurar Supabase Edge Functions (opcional)

```bash
# Instalar Supabase CLI
npm install -g supabase

# Login
supabase login

# Vincular al proyecto
supabase link --project-ref tu-project-ref

# Desplegar funciones
supabase functions deploy webhook-pago
supabase functions deploy ranking-update
```

Para el webhook de pagos, configurar en el proveedor de pago la URL:
```
https://tu-proyecto.supabase.co/functions/v1/webhook-pago
```

---

## Verificación del sistema

1. Ve a **WP Admin → PSP Sistema → Estado**
2. Todos los plugins deben aparecer como ✅ Activo
3. Haz clic en **Probar conexión Supabase** → debe mostrar ✅ Conexión exitosa

---

## Troubleshooting

### "PSP Core no activo" en otros plugins
→ Activar `psp-core` primero

### Error de conexión Supabase
→ Verificar que la URL no tenga `/` al final
→ Verificar que la Anon Key sea correcta (no la service key)
→ Comprobar que el proyecto Supabase esté activo (no en pausa)

### El selector territorial no carga
→ Verificar que la URL JSON externo sea accesible
→ Alternativamente, cargar datos desde Supabase (modo `supabase`)

### Error al crear usuario WordPress durante el registro
→ Verificar que el email no esté ya registrado en WordPress
→ Verificar que en **WP Admin → Ajustes → General** esté activada la opción "Cualquiera puede registrarse"
→ Revisar los logs de WordPress en `wp-content/debug.log` (activar `WP_DEBUG_LOG` si es necesario)

### El usuario no inicia sesión automáticamente tras el registro
→ Verificar que el plugin PSP Auth esté activo
→ Verificar que no hay plugins de seguridad bloqueando `wp_set_auth_cookie()`

### Los endpoints REST `/me`, `/wa-group`, `/pago-confirmar` devuelven 401
→ El usuario debe estar autenticado en WordPress (sesión activa con cookie)
→ Las peticiones AJAX deben incluir el nonce de WP REST: header `X-WP-Nonce: <nonce>` (ver `PSP_CONFIG.rest_nonce`)
→ Verificar que la sesión de WordPress no haya expirado

### Pagos no se activan automáticamente
→ Los métodos manuales (transferencia, efectivo) requieren activación manual desde Supabase Dashboard
→ Ir a **Supabase → Table Editor → pagos** → cambiar `estado` a `completado`
→ Ir a **Supabase → Table Editor → miembros** → cambiar `estado` a `activo`

---

## Contacto y soporte

- Repositorio: `iniciativa-psp/Movimiento`
- Sistema: `sistema.panamasinpobreza.org`
- Supabase docs: `https://supabase.com/docs`
