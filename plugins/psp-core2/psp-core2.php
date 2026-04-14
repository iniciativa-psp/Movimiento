<?php
/**
 * Plugin Name: PSP Core 2
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Núcleo v2 del sistema Movimiento PSP. Supabase REST client, configuración, helpers y REST API psp/v2. No depende de Supabase Auth ni de psp_jwt.
 * Version:     2.0.0
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-core2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Constantes ───────────────────────────────────────────────────────────────
define( 'PSP2_VERSION',    '2.0.0' );
define( 'PSP2_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PSP2_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PSP2_REST_NS',    'psp/v2' );

// ── Cargar dependencias ──────────────────────────────────────────────────────
require_once PSP2_PLUGIN_DIR . 'includes/api-client.php';
require_once PSP2_PLUGIN_DIR . 'includes/helpers.php';
require_once PSP2_PLUGIN_DIR . 'includes/security.php';
require_once PSP2_PLUGIN_DIR . 'includes/admin-settings.php';
require_once PSP2_PLUGIN_DIR . 'includes/rest-api.php';

// ── Activación ───────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'psp2_core_activate' );
function psp2_core_activate() {
    add_option( 'psp2_supabase_url',         '' );
    add_option( 'psp2_supabase_anon_key',    '' );
    add_option( 'psp2_supabase_service_key', '' );
    add_option( 'psp2_tenant_id',            'panama' );
    add_option( 'psp2_launch_date',          '2026-04-14T00:00:00' );
    add_option( 'psp2_campaign_start',       '2026-04-14T00:00:00' );
    add_option( 'psp2_campaign_end',         '2026-05-18T23:59:59' );
    add_option( 'psp2_membership_fee',       '1.00' );
    add_option( 'psp2_meta_miembros',        1000000 );
    add_option( 'psp2_meta_monto',           1000000 );
    // No flush_rewrite_rules() inside activation hook (avoid side effects).
}

// ── Encolar scripts globales (front-end) ─────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'psp2_core_enqueue' );
function psp2_core_enqueue() {
    wp_enqueue_style(
        'psp2-global',
        PSP2_PLUGIN_URL . 'assets/psp2-global.css',
        [],
        PSP2_VERSION
    );
    wp_enqueue_script(
        'psp2-global',
        PSP2_PLUGIN_URL . 'assets/psp2-global.js',
        [],
        PSP2_VERSION,
        true
    );
    wp_localize_script( 'psp2-global', 'PSP2_CONFIG', [
        'rest_url'       => rest_url( PSP2_REST_NS . '/' ),
        'rest_nonce'     => wp_create_nonce( 'wp_rest' ),
        'ajax_url'       => admin_url( 'admin-ajax.php' ),
        'nonce'          => wp_create_nonce( 'psp2_nonce' ),
        'tenant_id'      => get_option( 'psp2_tenant_id', 'panama' ),
        'launch_date'    => get_option( 'psp2_launch_date', '2026-04-14T00:00:00' ),
        'campaign_start' => get_option( 'psp2_campaign_start', '2026-04-14T00:00:00' ),
        'campaign_end'   => get_option( 'psp2_campaign_end',   '2026-05-18T23:59:59' ),
        'membership_fee' => (float) get_option( 'psp2_membership_fee', '1.00' ),
    ] );
}
