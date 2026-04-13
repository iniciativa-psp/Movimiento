<?php
if (!defined('ABSPATH')) exit;

// AJAX: enviar OTP
add_action('wp_ajax_nopriv_psp_send_otp', 'psp_ajax_send_otp');
add_action('wp_ajax_psp_send_otp',        'psp_ajax_send_otp');
function psp_ajax_send_otp(): void {
    if (!psp_rate_limit('otp_send', 5, 300)) {
        wp_send_json_error(['message' => 'Demasiados intentos. Espera 5 minutos.']);
    }
    if (!psp_verify_nonce()) wp_send_json_error(['message' => 'Sesión inválida']);

    $identifier = sanitize_text_field($_POST['identifier'] ?? '');
    if (!$identifier) wp_send_json_error(['message' => 'Identificador requerido']);

    // Llamar Supabase Auth OTP
    $is_phone = preg_match('/^\+?[0-9]{7,15}$/', $identifier);
    $url  = PSP_SUPABASE_URL . '/auth/v1/otp';
    $body = $is_phone
        ? ['phone' => $identifier]
        : ['email' => $identifier];

    $res = wp_remote_post($url, [
        'headers' => ['apikey' => PSP_SUPABASE_KEY, 'Content-Type' => 'application/json'],
        'body'    => wp_json_encode($body),
    ]);

    if (is_wp_error($res)) wp_send_json_error(['message' => 'Error enviando OTP']);

    $code = wp_remote_retrieve_response_code($res);
    if ($code === 200 || $code === 204) {
        wp_send_json_success(['message' => 'OTP enviado']);
    } else {
        wp_send_json_error(['message' => 'Error: ' . wp_remote_retrieve_body($res)]);
    }
}

// AJAX: verificar OTP
add_action('wp_ajax_nopriv_psp_verify_otp', 'psp_ajax_verify_otp');
add_action('wp_ajax_psp_verify_otp',        'psp_ajax_verify_otp');
function psp_ajax_verify_otp(): void {
    if (!psp_verify_nonce()) wp_send_json_error(['message' => 'Sesión inválida']);
    $identifier = sanitize_text_field($_POST['identifier'] ?? '');
    $otp        = sanitize_text_field($_POST['otp'] ?? '');
    if (!$identifier || !$otp) wp_send_json_error(['message' => 'Datos incompletos']);

    $is_phone = preg_match('/^\+?[0-9]{7,15}$/', $identifier);
    $url  = PSP_SUPABASE_URL . '/auth/v1/verify';
    $body = $is_phone
        ? ['type' => 'sms',   'phone' => $identifier, 'token' => $otp]
        : ['type' => 'email', 'email' => $identifier, 'token' => $otp];

    $res  = wp_remote_post($url, [
        'headers' => ['apikey' => PSP_SUPABASE_KEY, 'Content-Type' => 'application/json'],
        'body'    => wp_json_encode($body),
    ]);

    $data = json_decode(wp_remote_retrieve_body($res), true);
    if (isset($data['access_token'])) {
        // Buscar miembro en DB
        $miembro = PSP_Supabase::select('miembros', ['user_id' => 'eq.' . $data['user']['id'], 'limit' => 1]);
        wp_send_json_success([
            'jwt'      => $data['access_token'],
            'user_id'  => $data['user']['id'],
            'miembro'  => $miembro[0] ?? null,
            'redirect' => get_permalink(get_page_by_path('mi-cuenta')) ?: home_url('/mi-cuenta'),
        ]);
    } else {
        wp_send_json_error(['message' => 'Código inválido o expirado']);
    }
}

// AJAX: registro
add_action('wp_ajax_nopriv_psp_registro', 'psp_ajax_registro');
add_action('wp_ajax_psp_registro',        'psp_ajax_registro');
function psp_ajax_registro(): void {
    if (!psp_verify_nonce()) wp_send_json_error(['message' => 'Sesión inválida']);
    if (!psp_rate_limit('registro', 3, 300)) wp_send_json_error(['message' => 'Demasiados intentos']);

    $data = psp_sanitize_input([
        'nombre'          => $_POST['nombre']          ?? '',
        'celular'         => $_POST['celular']          ?? '',
        'email'           => $_POST['email']            ?? '',
        'tipo_miembro'    => $_POST['tipo_miembro']     ?? 'nacional',
        'provincia_id'    => $_POST['provincia_id']     ?? null,
        'distrito_id'     => $_POST['distrito_id']      ?? null,
        'corregimiento_id'=> $_POST['corregimiento_id'] ?? null,
        'comunidad_id'    => $_POST['comunidad_id']     ?? null,
        'pais_id'         => $_POST['pais_id']          ?? 'PA',
        'codigo_referido' => $_POST['codigo_referido']  ?? '',
    ]);

    if (!$data['nombre'] || !$data['celular']) {
        wp_send_json_error(['message' => 'Nombre y celular son obligatorios']);
    }

    // Verificar si ya existe
    $existe = PSP_Supabase::select('miembros', ['celular' => 'eq.' . $data['celular'], 'limit' => 1]);
    if ($existe) wp_send_json_error(['message' => 'Este celular ya está registrado']);

    $codigo = psp_generar_codigo('PSP');
    $data['codigo_referido_propio'] = $codigo;
    $data['tenant_id'] = get_option('psp_tenant_id', 'panama');
    $data['estado'] = 'pendiente_pago';

    // Buscar quién lo refirió
    if ($data['codigo_referido']) {
        $referidor = PSP_Supabase::select('miembros', ['codigo_referido_propio' => 'eq.' . $data['codigo_referido'], 'limit' => 1]);
        if ($referidor) $data['referido_por'] = $referidor[0]['id'];
    }
    unset($data['codigo_referido']);

    $nuevo = PSP_Supabase::insert('miembros', $data, true);
    if (!$nuevo) wp_send_json_error(['message' => 'Error al registrar. Intenta de nuevo.']);

    psp_audit_log('registro', ['tipo' => $data['tipo_miembro']], $nuevo[0]['id']);
    wp_send_json_success(['codigo' => $codigo, 'miembro_id' => $nuevo[0]['id']]);
}
