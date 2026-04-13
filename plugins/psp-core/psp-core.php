<?php
/**
 * Plugin Name: PSP Core
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Núcleo central del sistema Movimiento Panamá Sin Pobreza. Gestiona conexión Supabase, helpers globales y configuración multi-tenant.
 * Version:     1.0.0
 * Author:      Iván Centeno / PSP Dev Team
 * Text Domain: psp-core
 */

if (!defined('ABSPATH')) exit;

// ── Constantes ──────────────────────────────────────────────────────────────
define('PSP_VERSION',       '1.0.0');
define('PSP_PLUGIN_DIR',    plugin_dir_path(__FILE__));
define('PSP_PLUGIN_URL',    plugin_dir_url(__FILE__));
define('PSP_SUPABASE_URL',  get_option('psp_supabase_url', ''));
define('PSP_SUPABASE_KEY',  get_option('psp_supabase_anon_key', ''));
define('PSP_SUPABASE_SVC',  get_option('psp_supabase_service_key', ''));

// ── Cargar dependencias ──────────────────────────────────────────────────────
require_once PSP_PLUGIN_DIR . 'includes/api-client.php';
require_once PSP_PLUGIN_DIR . 'includes/helpers.php';
require_once PSP_PLUGIN_DIR . 'includes/security.php';
require_once PSP_PLUGIN_DIR . 'includes/admin-settings.php';

// ── Activación ───────────────────────────────────────────────────────────────
register_activation_hook(__FILE__, 'psp_core_activate');
function psp_core_activate() {
    add_option('psp_supabase_url', '');
    add_option('psp_supabase_anon_key', '');
    add_option('psp_supabase_service_key', '');
    add_option('psp_tenant_id', 'panama');
    add_option('psp_launch_date', '2026-05-12T09:00:00');
    add_option('psp_meta_objetivo_miembros', 1000000);
    add_option('psp_meta_objetivo_monto', 1000000);
}

// ── Encolar scripts globales ─────────────────────────────────────────────────
add_action('wp_enqueue_scripts', 'psp_core_enqueue');
function psp_core_enqueue() {
    wp_enqueue_script('psp-global', PSP_PLUGIN_URL . 'assets/psp-global.js', [], PSP_VERSION, true);
    wp_localize_script('psp-global', 'PSP_CONFIG', [
        'supabase_url' => PSP_SUPABASE_URL,
        'supabase_key' => PSP_SUPABASE_KEY,
        'tenant_id'    => get_option('psp_tenant_id'),
        'ajax_url'     => admin_url('admin-ajax.php'),
        'launch_date'  => get_option('psp_launch_date'),
        'nonce'        => wp_create_nonce('psp_nonce'),
    ]);
    wp_enqueue_style('psp-global', PSP_PLUGIN_URL . 'assets/psp-global.css', [], PSP_VERSION);
}
