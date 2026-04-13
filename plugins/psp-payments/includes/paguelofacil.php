<?php
if (!defined('ABSPATH')) exit;

function psp_paguelofacil_crear_sesion(string $pago_id, float $monto, string $referencia, string $tipo): ?string {
    $api_key    = get_option('psp_paguelofacil_key', '');
    $api_secret = get_option('psp_paguelofacil_secret', '');
    if (!$api_key) return null;

    $payload = [
        'publicKey'    => $api_key,
        'paymentItems' => [[
            'name'     => 'Membresía Panamá Sin Pobreza',
            'quantity' => 1,
            'price'    => $monto,
        ]],
        'orderId'       => $referencia,
        'returnUrl'     => home_url('/pago-confirmado/?pago_id=' . $pago_id),
        'cancelUrl'     => home_url('/pago-cancelado/'),
        'notifyUrl'     => home_url('/wp-json/psp/v1/webhook/paguelofacil'),
    ];

    $res = wp_remote_post('https://checkout.paguelofacil.com/api/v1/checkout/session', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($res)) return null;
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return $data['checkoutUrl'] ?? null;
}
