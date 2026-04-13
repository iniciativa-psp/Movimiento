<?php
if (!defined('ABSPATH')) exit;

/** Rate limiting simple via transients */
function psp_rate_limit(string $key, int $max = 10, int $window = 60): bool {
    $ip    = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $tkey  = 'psp_rl_' . md5($key . $ip);
    $count = (int) get_transient($tkey);
    if ($count >= $max) return false;
    set_transient($tkey, $count + 1, $window);
    return true;
}

/** Sanitizar datos de entrada */
function psp_sanitize_input(array $data): array {
    return array_map(function($v) {
        if (is_string($v)) return sanitize_text_field($v);
        if (is_array($v))  return psp_sanitize_input($v);
        return $v;
    }, $data);
}

/** Validar webhook signature */
function psp_validate_webhook_signature(string $payload, string $signature, string $secret): bool {
    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
}
