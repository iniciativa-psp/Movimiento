# Páginas WordPress — Shortcodes y Configuración

> **Campaña:** 14 abr 2026 → 18 may 2026 | Cuota de membresía: B/.1.00

---

## Página: "Inicio" (panamasinpobreza.org)

```
[psp_countdown]
[psp_termometro]
[psp_dashboard_publico]
```

---

## Página: "Únete" / Registro completo (MVP — Registro + Pago B/.1 + Éxito)

```
[psp_registro_completo]
```

Este shortcode incluye los 3 pasos del flujo MVP:
1. Formulario de registro (nombre, celular, email, territorio)
2. Selección de método de pago y registro del aporte B/.1
3. Pantalla de éxito con enlace de referido y botón de compartir WhatsApp

Para redirigir al perfil después del registro:
```
[psp_registro_completo redirect_url="/mi-cuenta/"]
```

### Página: "Registro" (solo formulario, sin pago)
```
[psp_registro]
```

---

## Página: "Apoyar" / Pagos adicionales

```
[psp_pagos]
```

---

## Página: "Mi Cuenta"

```
[psp_login]
[psp_perfil]
```

El shortcode `[psp_perfil]` incluye automáticamente:
- `[psp_mi_membresia]` — estado de membresía
- `[psp_mi_referido]` — código y link de referido con botones de compartir

Para mostrar por separado:
```
[psp_mi_referido]
[psp_mis_referidos]
[psp_mi_posicion]
```

---

## Página: "Grupos de WhatsApp"

```
[psp_mi_grupo_wa]
[psp_whatsapp_grupos]
```

- `[psp_mi_grupo_wa]` — muestra el grupo territorial asignado al miembro autenticado
- `[psp_whatsapp_grupos]` — lista todos los grupos disponibles con filtros

---

## Página: "Ranking"

```
[psp_ranking tipo="provincia" limite="20"]
[psp_ranking tipo="pais" limite="10"]
[psp_ranking tipo="embajador" limite="10"]
[psp_mapa]
```

---

## Página: "Retos y Gamificación"

```
[psp_mis_referidos]
[psp_mi_posicion]
[psp_ranking tipo="provincia" limite="5"]
```

---

## Widgets de sidebar / footer

### Termómetro de progreso
```
[psp_termometro]
```

### Contador regresivo
```
[psp_countdown]
```

### Mini-ranking
```
[psp_ranking tipo="provincia" limite="5"]
```

---

## REST API (para PWA / apps externas)

| Endpoint | Método | Auth | Descripción |
|----------|--------|------|-------------|
| `/wp-json/psp/v1/kpis` | GET | No | KPIs de campaña |
| `/wp-json/psp/v1/me` | GET | JWT | Perfil + referido + ranking |
| `/wp-json/psp/v1/wa-group` | GET | JWT | Grupos WA del miembro |
| `/wp-json/psp/v1/registro` | POST | No | Registrar miembro |
| `/wp-json/psp/v1/pago-confirmar` | POST | JWT | Confirmar pago |

Ver [SETUP.md](SETUP.md) para ejemplos de uso de la API.

