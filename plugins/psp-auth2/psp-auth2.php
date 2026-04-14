<?php
/**
 * Plugin Name: PSP Auth 2
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Autenticación WordPress nativa v2 para Movimiento PSP. Registro, login y perfil via shortcodes. Crea fila en Supabase `miembros` vinculada por wp_user_id. Sin Supabase Auth / sin psp_jwt.
 * Version:     2.0.0
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-auth2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Aviso si PSP Core 2 no está activo ───────────────────────────────────────
add_action( 'admin_notices', 'psp2_auth_check_core' );
function psp2_auth_check_core(): void {
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        echo '<div class="notice notice-error"><p><strong>PSP Auth 2:</strong> Requiere <strong>PSP Core 2</strong> activo.</p></div>';
    }
}

// ── Cargar handler de autenticación ─────────────────────────────────────────
add_action( 'plugins_loaded', 'psp2_auth_load' );
function psp2_auth_load(): void {
    require_once plugin_dir_path( __FILE__ ) . 'includes/auth-handler.php';
}

// ── Capturar ?ref= y guardar en cookie (30 días) ─────────────────────────────
add_action( 'init', 'psp2_capture_ref_param' );
function psp2_capture_ref_param(): void {
    if ( ! empty( $_GET['ref'] ) ) {
        $codigo = sanitize_text_field( wp_unslash( $_GET['ref'] ) );
        if ( preg_match( '/^[A-Z0-9\-]{5,30}$/', $codigo ) ) {
            setcookie( 'psp2_ref', $codigo, [
                'expires'  => time() + 86400 * 30,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ] );
            $_COOKIE['psp2_ref'] = $codigo;
        }
    }
}

// ── Shortcodes ───────────────────────────────────────────────────────────────
add_shortcode( 'psp2_login',             'psp2_login_shortcode' );
add_shortcode( 'psp2_registro_completo', 'psp2_registro_completo_shortcode' );
add_shortcode( 'psp2_perfil',            'psp2_perfil_shortcode' );

// ── Encolar assets ───────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'psp2_auth_enqueue' );
function psp2_auth_enqueue(): void {
    wp_enqueue_style(
        'psp2-auth',
        plugin_dir_url( __FILE__ ) . 'assets/psp2-auth.css',
        [ 'psp2-global' ],
        '2.0.0'
    );
    wp_enqueue_script(
        'psp2-auth',
        plugin_dir_url( __FILE__ ) . 'assets/psp2-auth.js',
        [ 'psp2-global' ],
        '2.0.0',
        true
    );
}
