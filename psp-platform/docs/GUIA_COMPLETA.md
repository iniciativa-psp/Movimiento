# 🇵🇦 Panamá Sin Pobreza — Guía Completa de Instalación

## Nivel de conocimiento requerido: 1/5 ✅ (te guiamos paso a paso)

---

## 🗂️ ESTRUCTURA DEL PROYECTO

```
psp-platform/
├── wordpress/wp-content/plugins/
│   ├── psp-core/          ← Núcleo: conexión Supabase + helpers
│   ├── psp-auth/          ← Login por celular/código OTP
│   ├── psp-payments/      ← Yappy, Clave, Tarjeta, ACH, etc.
│   ├── psp-dashboard/     ← KPIs en tiempo real + mapa + ranking
│   ├── psp-referidos/     ← Códigos, puntos, niveles, compartir
│   └── psp-erp/           ← Contabilidad, facturas DGI, pagos admin
├── supabase/functions/
│   ├── crear-pago/        ← Edge Function: crear intención de pago
│   ├── webhook-pago/      ← Edge Function: recibir webhooks
│   ├── factura-generar/   ← Edge Function: XML DGI + PAC
│   └── ranking-update/    ← Edge Function: actualizar ranking (cron)
├── sql/
│   ├── 01_schema_completo.sql    ← Todas las tablas
│   └── 02_functions_triggers.sql ← Funciones y triggers
├── pwa/
│   ├── manifest.json      ← App installable
│   └── service-worker.js  ← Offline + push notifications
└── scripts/
    ├── install.sh          ← Instala todo automáticamente
    └── deploy-functions.sh ← Despliega Edge Functions
```

---

## ⚡ PASO 1: Crear cuenta Supabase (GRATIS)

1. Ve a **https://supabase.com** y crea una cuenta
2. Crea un **nuevo proyecto**
3. Guarda estos datos:
   - `Project URL`: https://TU-ID.supabase.co
   - `anon/public key`: eyJ...
   - `service_role key`: eyJ... (secreto)

---

## ⚡ PASO 2: Crear la base de datos

1. En Supabase → **SQL Editor**
2. Pega y ejecuta: `sql/01_schema_completo.sql`
3. Pega y ejecuta: `sql/02_functions_triggers.sql`
4. ✅ Listo — todas las tablas creadas

---

## ⚡ PASO 3: Instalar plugins en WordPress

### Opción A (automática):
```bash
WP_PATH=/var/www/html bash scripts/install.sh
```

### Opción B (manual):
1. Copia la carpeta `wordpress/wp-content/plugins/psp-*` a tu WordPress
2. WordPress Admin → Plugins → **Activar** todos los PSP-*

---

## ⚡ PASO 4: Configurar credenciales

1. WordPress Admin → **🇵🇦 PSP Sistema** → Configuración
2. Ingresa:
   - Supabase URL
   - Anon Key
   - Service Key (en privado)
3. Guarda

---

## ⚡ PASO 5: Crear las páginas en WordPress

| Página              | Shortcode                   |
|---------------------|-----------------------------|
| Inicio / Landing    | `[psp_dashboard_publico]` `[psp_termometro]` |
| Registro            | `[psp_registro]`            |
| Mi Cuenta           | `[psp_login]` `[psp_mi_referido]` `[psp_mis_referidos]` |
| Apoyar              | `[psp_pagos]`               |
| Ranking             | `[psp_ranking]` `[psp_mapa]` |
| Dashboard Público   | `[psp_dashboard_publico]`   |

---

## ⚡ PASO 6: Configurar pagos

### Yappy:
1. Contacta a tu banco (Banco General) para activar Yappy Business
2. Ingresa el número Yappy en: PSP → Configuración → Yappy

### PagueloFacil (Tarjetas + Sistema Clave):
1. Regístrate en https://paguelofacil.com
2. Obtén API Key y Secret
3. WordPress Admin → PSP → Configuración → PagueloFacil

### Webhooks (obligatorio para confirmación automática):
Configura en cada proveedor la URL:
```
https://TU-DOMINIO.COM/wp-json/psp/v1/webhook/paguelofacil
https://TU-DOMINIO.COM/wp-json/psp/v1/webhook/yappy
```

---

## ⚡ PASO 7: Deployar Edge Functions (Supabase)

```bash
npm install -g supabase
supabase login
export SUPABASE_PROJECT_ID="tu-project-id"
bash scripts/deploy-functions.sh
```

Configurar secrets DGI/PAC:
```bash
supabase secrets set PSP_RUC="TU-RUC"         --project-ref $PROJ
supabase secrets set PSP_DV="TU-DV"            --project-ref $PROJ
supabase secrets set PSP_PAC_URL="URL-PAC"     --project-ref $PROJ
supabase secrets set PSP_PAC_TOKEN="TOKEN-PAC" --project-ref $PROJ
```

---

## 📱 PASO 8: Activar PWA

Añade en tu `functions.php` de WordPress:
```php
add_action('wp_head', function() {
    echo '<link rel="manifest" href="/pwa/manifest.json">';
    echo '<meta name="theme-color" content="#0B5E43">';
});
add_action('wp_footer', function() {
    echo '<script>if("serviceWorker" in navigator) navigator.serviceWorker.register("/pwa/service-worker.js");</script>';
});
```

---

## 🔒 SEGURIDAD INCLUIDA

- ✅ Nonces en todos los AJAX
- ✅ Rate limiting por IP
- ✅ Sanitización de inputs
- ✅ Row Level Security (RLS) en Supabase
- ✅ Validación de webhook signatures
- ✅ Auditoría completa de acciones
- ✅ JWT verificado desde Supabase Auth

---

## 📊 DOMINIOS RECOMENDADOS

| Dominio                        | Uso                           |
|--------------------------------|-------------------------------|
| `panamasinpobreza.org`         | Landing, registro, PWA pública |
| `sistema.panamasinpobreza.org` | WordPress Admin, plugins       |

---

## 🆘 SOPORTE

Si tienes dudas, revisa:
1. WordPress Admin → PSP → Estado del sistema
2. Supabase → Logs → Edge Functions
3. WordPress → Herramientas → Errores

---

*Versión 1.0.0 — Panamá Sin Pobreza 2026*
