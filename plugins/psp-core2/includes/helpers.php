<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Helpers de autenticación ─────────────────────────────────────────────────

/**
 * Devuelve la fila del miembro en Supabase correspondiente al usuario WP actual.
 * Retorna null si no está logueado o si no se encuentra registro.
 *
 * @param array $select Columnas a seleccionar (array de strings).
 * @return array|null
 */
function psp2_get_current_member( array $select = [] ): ?array {
    if ( ! is_user_logged_in() ) {
        return null;
    }
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        return null;
    }
    $wp_user_id = (int) get_current_user_id();
    $cols = $select ? implode( ',', array_map( 'sanitize_key', $select ) ) : '*';
    $rows = PSP2_Supabase::select( 'miembros', [
        'wp_user_id' => 'eq.' . $wp_user_id,
        'select'     => $cols,
        'limit'      => 1,
    ] );
    return ( ! empty( $rows ) && is_array( $rows ) ) ? $rows[0] : null;
}

/**
 * Genera un código único de referido.
 *
 * @param string $prefix Prefijo del código.
 * @return string
 */
function psp2_generar_codigo( string $prefix = 'PSP' ): string {
    return strtoupper( $prefix . '-' . bin2hex( random_bytes( 5 ) ) );
}

/**
 * Obtiene la IP real del cliente (considera Cloudflare / proxy de confianza).
 *
 * @return string
 */
function psp2_get_client_ip(): string {
    $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );

    $trusted = [ '127.0.0.1', '::1' ];
    if ( in_array( $ip, $trusted, true ) ) {
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            $cf = sanitize_text_field( $_SERVER['HTTP_CF_CONNECTING_IP'] );
            if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) {
                return $cf;
            }
        }
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $parts     = explode( ',', sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            $forwarded = trim( $parts[0] );
            if ( filter_var( $forwarded, FILTER_VALIDATE_IP ) ) {
                return $forwarded;
            }
        }
    }
    return $ip;
}

/**
 * Formatea un monto a Balboas / USD.
 *
 * @param float $monto
 * @return string
 */
function psp2_formato_monto( float $monto ): string {
    return 'B/.' . number_format( $monto, 2, '.', ',' );
}

/**
 * Registra un evento en la tabla `auditoria` de Supabase (no fatal si falla).
 *
 * @param string      $accion
 * @param array       $datos
 * @param string|null $miembro_id
 */
function psp2_audit_log( string $accion, array $datos = [], ?string $miembro_id = null ): void {
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        return;
    }
    PSP2_Supabase::insert( 'auditoria', [
        'accion'     => $accion,
        'miembro_id' => $miembro_id,
        'datos'      => wp_json_encode( $datos ),
        'ip'         => psp2_get_client_ip(),
        'user_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
    ], true );
}
