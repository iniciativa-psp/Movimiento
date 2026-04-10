<?php
if (!defined('ABSPATH')) exit;

// AJAX crear intención de pago
add_action('wp_ajax_psp_crear_pago',        'psp_ajax_crear_pago');
add_action('wp_ajax_nopriv_psp_crear_pago', 'psp_ajax_crear_pago');
function psp_ajax_crear_pago(): void {
    if (!psp_verify_nonce()) wp_send_json_error(['message' => 'Nonce inválido']);
    if (!psp_rate_limit('crear_pago', 10, 60)) wp_send_json_error(['message' => 'Rate limit']);

    $miembro_id = sanitize_text_field($_POST['miembro_id'] ?? '');
    $monto      = (float) ($_POST['monto'] ?? 0);
    $metodo     = sanitize_text_field($_POST['metodo'] ?? '');
    $tipo       = sanitize_text_field($_POST['tipo'] ?? 'nacional');

    if (!$miembro_id || $monto <= 0 || !$metodo) {
        wp_send_json_error(['message' => 'Datos incompletos']);
    }

    $referencia = 'PSP-' . strtoupper(bin2hex(random_bytes(5)));

    $pago = PSP_Supabase::insert('pagos', [
        'miembro_id'  => $miembro_id,
        'monto'       => $monto,
        'metodo'      => $metodo,
        'tipo_membresia' => $tipo,
        'estado'      => 'pendiente',
        'referencia'  => $referencia,
        'tenant_id'   => get_option('psp_tenant_id', 'panama'),
    ], true);

    if (!$pago) wp_send_json_error(['message' => 'Error creando pago']);

    $response = [
        'pago_id'    => $pago[0]['id'],
        'referencia' => $referencia,
        'monto'      => $monto,
    ];

    // Checkout URL por proveedor
    switch ($metodo) {
        case 'clave':
        case 'tarjeta':
            $checkout = psp_paguelofacil_crear_sesion($pago[0]['id'], $monto, $referencia, $metodo);
            if ($checkout) $response['checkout_url'] = $checkout;
            break;
    }

    wp_send_json_success($response);
}

// AJAX confirmar pago manual
add_action('wp_ajax_psp_confirmar_pago_manual',        'psp_ajax_confirmar_manual');
add_action('wp_ajax_nopriv_psp_confirmar_pago_manual', 'psp_ajax_confirmar_manual');
function psp_ajax_confirmar_manual(): void {
    if (!psp_verify_nonce()) wp_send_json_error(['message' => 'Nonce inválido']);
    $pago_id = sanitize_text_field($_POST['pago_id'] ?? '');
    if (!$pago_id) wp_send_json_error(['message' => 'ID inválido']);

    PSP_Supabase::update('pagos', ['estado' => 'pendiente_validacion'], ['id' => 'eq.' . $pago_id]);
    wp_send_json_success(['message' => 'Registrado para validación']);
}

// AJAX subir comprobante
add_action('wp_ajax_psp_subir_comprobante',        'psp_ajax_subir_comprobante');
add_action('wp_ajax_nopriv_psp_subir_comprobante', 'psp_ajax_subir_comprobante');
function psp_ajax_subir_comprobante(): void {
    if (!psp_verify_nonce()) wp_send_json_error(['message' => 'Nonce inválido']);
    if (!isset($_FILES['comprobante'])) wp_send_json_error(['message' => 'Archivo requerido']);

    $pago_id = sanitize_text_field($_POST['pago_id'] ?? '');
    $allowed = ['image/jpeg','image/png','image/gif','application/pdf'];
    if (!in_array($_FILES['comprobante']['type'], $allowed)) {
        wp_send_json_error(['message' => 'Tipo de archivo no permitido']);
    }

    $upload = wp_handle_upload($_FILES['comprobante'], ['test_form' => false]);
    if (isset($upload['error'])) wp_send_json_error(['message' => $upload['error']]);

    PSP_Supabase::update('pagos', [
        'comprobante_url' => $upload['url'],
        'estado'          => 'pendiente_validacion',
    ], ['id' => 'eq.' . $pago_id]);

    wp_send_json_success(['url' => $upload['url']]);
}
