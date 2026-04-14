<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Seguridad y rate limiting ────────────────────────────────────────────────

/**
 * Rate limiting simple via WP transients.
 *
 * @param string $key    Clave única de la acción.
 * @param int    $max    Intentos máximos permitidos.
 * @param int    $window Ventana de tiempo en segundos.
 * @return bool  true si la acción está permitida, false si se alcanzó el límite.
 */
function psp2_rate_limit( string $key, int $max = 10, int $window = 60 ): bool {
    $ip      = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
    // Combine IP + user ID (when logged in) to avoid penalizing shared IPs
    $user_id = is_user_logged_in() ? (string) get_current_user_id() : 'guest';
    $tkey    = 'psp2_rl_' . md5( $key . $ip . $user_id );
    $count   = (int) get_transient( $tkey );
    if ( $count >= $max ) {
        return false;
    }
    set_transient( $tkey, $count + 1, $window );
    return true;
}

/**
 * Verifica el nonce PSP2 para peticiones AJAX.
 *
 * @return bool
 */
function psp2_verify_nonce(): bool {
    $nonce = $_REQUEST['psp2_nonce'] ?? '';
    return wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'psp2_nonce' ) !== false;
}

/**
 * Sanitiza recursivamente un array de entrada.
 *
 * @param array $data
 * @return array
 */
function psp2_sanitize_input( array $data ): array {
    return array_map( function ( $v ) {
        if ( is_string( $v ) ) {
            return sanitize_text_field( $v );
        }
        if ( is_array( $v ) ) {
            return psp2_sanitize_input( $v );
        }
        return $v;
    }, $data );
}
