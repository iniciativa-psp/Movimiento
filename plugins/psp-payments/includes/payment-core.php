<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Retorna el precio correcto según tipo (membresía o producto)
 * Integra psp-membresias y psp-productos si están activos
 */
function psp_get_precio( $tipo, $cantidad = 1 ) {
    // Desde psp-membresias (si está activo)
    if ( function_exists('psp_get_membresias_config') ) {
        foreach ( psp_get_membresias_config() as $m ) {
            if ( $m['tipo'] === $tipo ) {
                return (float) get_option( 'psp_precio_' . $m['tipo'], $m['precio_default'] ) * $cantidad;
            }
        }
    }
    // Desde psp-productos (si está activo)
    if ( function_exists('psp_get_productos_config') ) {
        foreach ( psp_get_productos_config() as $p ) {
            if ( $p['slug'] === $tipo ) {
                return (float) get_option( $p['opcion_precio'], $p['precio_default'] ) * $cantidad;
            }
        }
        // Planes SIGS
        if ( function_exists('psp_get_sigs_planes') ) {
            foreach ( psp_get_sigs_planes() as $plan ) {
                if ( $plan['slug'] === $tipo ) {
                    return (float) get_option( $plan['opcion_precio'] ?: '', $plan['precio_default'] );
                }
            }
        }
    }
    // Fallback a opciones directas
    $fallback = [
        'nacional'=>5,'internacional'=>10,'actor'=>25,'sector'=>50,
        'hogar_solidario'=>15,'productor'=>20,'comunicador'=>15,
        'influencer'=>25,'embajador'=>0,'voluntario'=>0,'planton'=>2,
    ];
    return (float)( $fallback[$tipo] ?? 0 ) * $cantidad;
}

/**
 * Retorna el label del tipo para la factura
 */
function psp_get_tipo_label( $tipo ) {
    if ( function_exists('psp_get_membresias_nombres') ) {
        $nombres = psp_get_membresias_nombres();
        if ( isset($nombres[$tipo]) ) return $nombres[$tipo];
    }
    if ( function_exists('psp_get_productos_config') ) {
        foreach ( psp_get_productos_config() as $p ) {
            if ( $p['slug'] === $tipo ) return $p['nombre'];
        }
    }
    return ucfirst( str_replace('_',' ',$tipo) );
}

// ── AJAX: crear intención de pago ─────────────────────────────────────────────
add_action( 'wp_ajax_psp_crear_pago',        'psp_ajax_crear_pago' );
add_action( 'wp_ajax_nopriv_psp_crear_pago', 'psp_ajax_crear_pago' );
function psp_ajax_crear_pago() {
    if ( ! psp_verify_nonce() )                         wp_send_json_error(['message'=>'Nonce inválido']);
    if ( ! psp_rate_limit('crear_pago',10,60) )         wp_send_json_error(['message'=>'Rate limit']);

    $miembro_id = sanitize_text_field( $_POST['miembro_id']     ?? '' );
    $tipo       = sanitize_text_field( $_POST['tipo']           ?? 'nacional' );
    $metodo     = sanitize_text_field( $_POST['metodo']         ?? '' );
    $cantidad   = max(1, (int)( $_POST['cantidad']              ?? 1 ) );
    // monto puede venir del front o se calcula aquí (más seguro calcularlo aquí)
    $monto_front = (float)( $_POST['monto'] ?? 0 );

    if ( ! $miembro_id || ! $metodo ) wp_send_json_error(['message'=>'Datos incompletos']);

    // Calcular precio desde configuración centralizada (no confiar en el front)
    $monto = psp_get_precio( $tipo, $cantidad );
    // Si el precio calculado es 0 (embajador/voluntario) se acepta el del front solo si es 0
    if ( $monto <= 0 && $monto_front > 0 ) $monto = $monto_front;

    $referencia = 'PSP-' . strtoupper( substr( bin2hex(random_bytes(5)), 0, 10 ) );

    $pago = PSP_Supabase::insert( 'pagos', [
        'miembro_id'     => $miembro_id,
        'monto'          => $monto,
        'metodo'         => $metodo,
        'tipo_membresia' => $tipo,
        'estado'         => 'pendiente',
        'referencia'     => $referencia,
        'tenant_id'      => get_option('psp_tenant_id','panama'),
    ], true );

    if ( ! $pago ) wp_send_json_error(['message'=>'Error creando pago']);

    $response = [
        'pago_id'    => $pago[0]['id'],
        'referencia' => $referencia,
        'monto'      => $monto,
        'tipo_label' => psp_get_tipo_label($tipo),
    ];

    // Datos de checkout por método
    switch ( $metodo ) {
        case 'clave':
        case 'tarjeta':
            if ( function_exists('psp_paguelofacil_crear_sesion') ) {
                $url = psp_paguelofacil_crear_sesion( $pago[0]['id'], $monto, $referencia, $metodo );
                if ($url) $response['checkout_url'] = $url;
            }
            break;
        case 'yappy':
            $response['numero_yappy'] = get_option('psp_yappy_numero','');
            $response['nombre_yappy'] = get_option('psp_yappy_nombre','Panamá Sin Pobreza');
            break;
        case 'ach':
        case 'transferencia':
            $response['banco']   = get_option('psp_banco_nombre','Banco General');
            $response['cuenta']  = get_option('psp_banco_cuenta','');
            $response['titular'] = 'Iniciativa Panamá Sin Pobreza';
            break;
    }

    wp_send_json_success($response);
}

// ── AJAX: confirmar pago manual ───────────────────────────────────────────────
add_action( 'wp_ajax_psp_confirmar_pago_manual',        'psp_ajax_confirmar_manual' );
add_action( 'wp_ajax_nopriv_psp_confirmar_pago_manual', 'psp_ajax_confirmar_manual' );
function psp_ajax_confirmar_manual() {
    if ( ! psp_verify_nonce() ) wp_send_json_error();
    $pago_id = sanitize_text_field( $_POST['pago_id'] ?? '' );
    if ( ! $pago_id ) wp_send_json_error(['message'=>'ID inválido']);
    PSP_Supabase::update('pagos',['estado'=>'pendiente_validacion'],['id'=>'eq.'.$pago_id]);
    wp_send_json_success(['message'=>'Registrado para validación']);
}

// ── AJAX: subir comprobante ───────────────────────────────────────────────────
add_action( 'wp_ajax_psp_subir_comprobante',        'psp_ajax_subir_comprobante' );
add_action( 'wp_ajax_nopriv_psp_subir_comprobante', 'psp_ajax_subir_comprobante' );
function psp_ajax_subir_comprobante() {
    if ( ! psp_verify_nonce() ) wp_send_json_error(['message'=>'Nonce inválido']);
    if ( ! isset($_FILES['comprobante']) ) wp_send_json_error(['message'=>'Archivo requerido']);
    $pago_id = sanitize_text_field($_POST['pago_id']??'');
    $allowed = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
    if ( ! in_array($_FILES['comprobante']['type'],$allowed) ) wp_send_json_error(['message'=>'Tipo no permitido']);
    $upload = wp_handle_upload($_FILES['comprobante'],['test_form'=>false]);
    if ( isset($upload['error']) ) wp_send_json_error(['message'=>$upload['error']]);
    PSP_Supabase::update('pagos',['comprobante_url'=>$upload['url'],'estado'=>'pendiente_validacion'],['id'=>'eq.'.$pago_id]);
    wp_send_json_success(['url'=>$upload['url']]);
}
