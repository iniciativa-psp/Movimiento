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

// ── Compatibilidad legacy: exponer PSP_Supabase como alias de PSP2_Supabase ──
// Muchos plugins v2 y legacy aún validan PSP Core con class_exists('PSP_Supabase').
// Este shim hace que esas comprobaciones pasen sin modificar esos plugins.
if ( class_exists( 'PSP2_Supabase' ) && ! class_exists( 'PSP_Supabase' ) ) {
    // phpcs:ignore Generic.Files.OneClassPerFile.MultipleFound
    class PSP_Supabase extends PSP2_Supabase {}
}

// ── Constantes legacy para plugins que las leen directamente ─────────────────
if ( ! defined( 'PSP_SUPABASE_URL' ) ) {
    define( 'PSP_SUPABASE_URL', get_option( 'psp2_supabase_url', get_option( 'psp_supabase_url', '' ) ) );
}
if ( ! defined( 'PSP_SUPABASE_KEY' ) ) {
    define( 'PSP_SUPABASE_KEY', get_option( 'psp2_supabase_anon_key', get_option( 'psp_supabase_anon_key', '' ) ) );
}
if ( ! defined( 'PSP_SUPABASE_SVC' ) ) {
    define( 'PSP_SUPABASE_SVC', get_option( 'psp2_supabase_service_key', get_option( 'psp_supabase_service_key', '' ) ) );
}
if ( ! defined( 'PSP_TENANT_ID' ) ) {
    define( 'PSP_TENANT_ID', get_option( 'psp2_tenant_id', get_option( 'psp_tenant_id', 'panama' ) ) );
}

require_once PSP2_PLUGIN_DIR . 'includes/helpers.php';
require_once PSP2_PLUGIN_DIR . 'includes/security.php';
require_once PSP2_PLUGIN_DIR . 'includes/admin-settings.php';
require_once PSP2_PLUGIN_DIR . 'includes/rest-api.php';

// ── Sincronizar opciones psp2_* → psp_* (solo si legacy está vacío) ──────────
// Esto permite que plugins que aún leen psp_* obtengan la configuración v2
// sin necesidad de modificarlos. Nunca sobreescribe opciones legacy no vacías.
add_action( 'plugins_loaded', 'psp2_sync_legacy_options', 20 );
function psp2_sync_legacy_options(): void {
    $map = [
        'psp_supabase_url'         => 'psp2_supabase_url',
        'psp_supabase_anon_key'    => 'psp2_supabase_anon_key',
        'psp_supabase_service_key' => 'psp2_supabase_service_key',
        'psp_tenant_id'            => 'psp2_tenant_id',
        'psp_launch_date'          => 'psp2_launch_date',
        'psp_campaign_start'       => 'psp2_campaign_start',
        'psp_campaign_end'         => 'psp2_campaign_end',
    ];
    foreach ( $map as $legacy_key => $v2_key ) {
        $v2_val     = get_option( $v2_key, '' );
        $legacy_val = get_option( $legacy_key, '' );
        if ( ! empty( $v2_val ) && empty( $legacy_val ) ) {
            update_option( $legacy_key, $v2_val );
        }
    }
}

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
