<?php
if (!defined('ABSPATH')) exit;

/** Genera código único de referido */
function psp_generar_codigo(string $prefix = 'PSP'): string {
    return strtoupper($prefix . '-' . bin2hex(random_bytes(5)));
}

/** Formatea monto a USD */
function psp_formato_monto(float $monto): string {
    return '$' . number_format($monto, 2, '.', ',');
}

/** Retorna el ID de miembro de la sesión actual */
function psp_get_miembro_id(): ?string {
    return $_SESSION['psp_miembro_id'] ?? null;
}

/** Retorna el JWT del usuario actual */
function psp_get_jwt(): ?string {
    return $_COOKIE['psp_jwt'] ?? null;
}

/** Log de auditoría */
function psp_audit_log(string $accion, array $datos = [], ?string $miembro_id = null): void {
    PSP_Supabase::insert('auditoria', [
        'accion'     => $accion,
        'miembro_id' => $miembro_id ?? psp_get_miembro_id(),
        'datos'      => wp_json_encode($datos),
        'ip'         => psp_get_client_ip(),
        'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
    ], true);
}

/** Validar nonce PSP */
function psp_verify_nonce(): bool {
    $nonce = $_REQUEST['psp_nonce'] ?? '';
    return wp_verify_nonce($nonce, 'psp_nonce') !== false;
}

/**
 * Obtiene la IP real del cliente considerando proxies confiables.
 * Solo confía en X-Forwarded-For si el servidor está detrás de un proxy.
 */
function psp_get_client_ip(): string {
    $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');

    // Si está detrás de un proxy de confianza (ej. Cloudflare, LB interno)
    // solo considerar CF-Connecting-IP o X-Forwarded-For si viene de localhost/red interna
    $trusted_proxies = ['127.0.0.1', '::1'];
    if (in_array($ip, $trusted_proxies, true)) {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $cf = sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
            if (filter_var($cf, FILTER_VALIDATE_IP)) {
                return $cf;
            }
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']));
            $forwarded = trim($parts[0]);
            if (filter_var($forwarded, FILTER_VALIDATE_IP)) {
                return $forwarded;
            }
        }
    }

    return $ip;
}
