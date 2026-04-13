#!/bin/bash
# ============================================================
# PSP Install Script — Panamá Sin Pobreza
# Instala y configura la plataforma desde cero
# ============================================================

set -e
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
log()  { echo -e "${GREEN}[PSP]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
err()  { echo -e "${RED}[ERR]${NC} $1"; exit 1; }

log "🇵🇦 Iniciando instalación Panamá Sin Pobreza..."

# Verificar requerimientos
command -v php  >/dev/null 2>&1 || err "PHP no instalado"
command -v wp   >/dev/null 2>&1 || warn "WP-CLI no instalado (recomendado)"
command -v supabase >/dev/null 2>&1 || warn "Supabase CLI no instalado"

WP_PATH=${WP_PATH:-"/var/www/html"}
PSP_DIR="$(dirname "$0")/.."

# Copiar plugins
log "📦 Copiando plugins..."
cp -r "$PSP_DIR/wordpress/wp-content/plugins/"* "$WP_PATH/wp-content/plugins/"
log "✅ Plugins copiados"

# Activar plugins vía WP-CLI
if command -v wp >/dev/null 2>&1; then
    log "🔌 Activando plugins..."
    wp plugin activate psp-core      --path="$WP_PATH" --allow-root
    wp plugin activate psp-auth      --path="$WP_PATH" --allow-root
    wp plugin activate psp-payments  --path="$WP_PATH" --allow-root
    wp plugin activate psp-dashboard --path="$WP_PATH" --allow-root
    wp plugin activate psp-referidos --path="$WP_PATH" --allow-root
    wp plugin activate psp-erp       --path="$WP_PATH" --allow-root
    log "✅ Plugins activados"
fi

log ""
log "🎉 Instalación completa!"
log ""
log "📋 PASOS SIGUIENTES:"
log "  1. Ve a WordPress Admin → PSP Sistema → Configuración"
log "  2. Ingresa tu Supabase URL y Keys"
log "  3. Corre el SQL en Supabase: sql/01_schema_completo.sql"
log "  4. Corre el SQL en Supabase: sql/02_functions_triggers.sql"
log "  5. Deploya las Edge Functions: scripts/deploy-functions.sh"
log "  6. Configura los shortcodes en tus páginas"
log ""
log "📚 Shortcodes disponibles:"
log "  [psp_registro]          — Formulario de registro"
log "  [psp_login]             — Login con OTP"
log "  [psp_pagos]             — Botones de pago"
log "  [psp_dashboard_publico] — Dashboard en tiempo real"
log "  [psp_termometro]        — Termómetro de miembros"
log "  [psp_ranking]           — Ranking territorial"
log "  [psp_mapa]              — Mapa interactivo"
log "  [psp_mi_referido]       — Código y enlaces de referido"
log "  [psp_mis_referidos]     — Lista de referidos del usuario"
