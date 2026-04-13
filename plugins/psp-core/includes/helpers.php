<?php
if (!defined('ABSPATH')) exit;

/** Genera código único de referido */
function psp_generar_codigo(string $prefix = 'PSP'): string {
    return strtoupper($prefix . '-' . bin2hex(random_bytes(4)));
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
        'ip'         => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
    ], true);
}

/** Validar nonce PSP */
function psp_verify_nonce(): bool {
    $nonce = $_REQUEST['psp_nonce'] ?? '';
    return wp_verify_nonce($nonce, 'psp_nonce') !== false;
}
