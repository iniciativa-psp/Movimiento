<?php
if (!defined('ABSPATH')) exit;
// Integración Yappy (Banco General)
// Yappy Business requiere contrato directo con Banco General.
// Esta función genera el QR de pago cuando se tenga la API.
function psp_yappy_generar_qr(float $monto, string $referencia): ?string {
    $numero = get_option('psp_yappy_numero', '');
    if (!$numero) return null;
    // Con API Yappy Business: POST /api/payment/generate
    // Por ahora retorna null y el frontend muestra el número manual
    return null;
}
