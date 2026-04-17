<?php
/**
 * Plugin Name: PSP Core
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Núcleo central del sistema Movimiento Panamá Sin Pobreza. Gestiona conexión Supabase, helpers globales, configuración multi-tenant y REST API.
 * Version:     1.1.0
 * Author:      Iván Centeno / PSP Dev Team
 * Text Domain: psp-core
 */

if (!defined('ABSPATH')) exit;

// ── Constantes ──────────────────────────────────────────────────────────────
define('PSP_VERSION',       '1.1.0');
define('PSP_PLUGIN_DIR',    plugin_dir_path(__FILE__));
define('PSP_PLUGIN_URL',    plugin_dir_url(__FILE__));
define('PSP_SUPABASE_URL',  get_option('psp_supabase_url', ''));
define('PSP_SUPABASE_KEY',  get_option('psp_supabase_anon_key', ''));
define('PSP_SUPABASE_SVC',  get_option('psp_supabase_service_key', ''));

// ── Constantes de campaña ────────────────────────────────────────────────────
define('PSP_CAMPAIGN_START',   get_option('psp_campaign_start',   '2026-04-14T00:00:00'));
define('PSP_CAMPAIGN_END',     get_option('psp_campaign_end',     '2026-05-18T23:59:59'));
define('PSP_MEMBERSHIP_FEE',   (float) get_option('psp_membership_fee', '1.00'));
define('PSP_REST_NAMESPACE',   'psp/v1');

// ── Cargar dependencias ──────────────────────────────────────────────────────
require_once PSP_PLUGIN_DIR . 'includes/api-client.php';
require_once PSP_PLUGIN_DIR . 'includes/helpers.php';
require_once PSP_PLUGIN_DIR . 'includes/security.php';
require_once PSP_PLUGIN_DIR . 'includes/admin-settings.php';
require_once PSP_PLUGIN_DIR . 'includes/rest-api.php';

// ── Activación ───────────────────────────────────────────────────────────────
register_activation_hook(__FILE__, 'psp_core_activate');
function psp_core_activate() {
    add_option('psp_supabase_url', '');
    add_option('psp_supabase_anon_key', '');
    add_option('psp_supabase_service_key', '');
    add_option('psp_tenant_id', 'panama');
    add_option('psp_launch_date',    '2026-04-14T00:00:00');
    add_option('psp_campaign_start', '2026-04-14T00:00:00');
    add_option('psp_campaign_end',   '2026-05-18T23:59:59');
    add_option('psp_membership_fee', '1.00');
    add_option('psp_meta_objetivo_miembros', 1000000);
    add_option('psp_meta_objetivo_monto',    1000000);
    flush_rewrite_rules();
}

// ── Encolar scripts globales ─────────────────────────────────────────────────
add_action('wp_enqueue_scripts', 'psp_core_enqueue');
function psp_core_enqueue() {
    wp_enqueue_script('psp-global', PSP_PLUGIN_URL . 'assets/psp-global.js', [], PSP_VERSION, true);
    wp_localize_script('psp-global', 'PSP_CONFIG', [
        'supabase_url'    => PSP_SUPABASE_URL,
        'supabase_key'    => PSP_SUPABASE_KEY,
        'tenant_id'       => get_option('psp_tenant_id'),
        'ajax_url'        => admin_url('admin-ajax.php'),
        'rest_url'        => rest_url(PSP_REST_NAMESPACE . '/'),
        'rest_nonce'      => wp_create_nonce('wp_rest'),
        'launch_date'     => get_option('psp_launch_date'),
        'campaign_start'  => PSP_CAMPAIGN_START,
        'campaign_end'    => PSP_CAMPAIGN_END,
        'membership_fee'  => PSP_MEMBERSHIP_FEE,
        'nonce'           => wp_create_nonce('psp_nonce'),
    ]);
    wp_enqueue_style('psp-global', PSP_PLUGIN_URL . 'assets/psp-global.css', [], PSP_VERSION);
}
