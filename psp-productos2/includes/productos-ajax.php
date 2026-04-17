<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── AJAX: crear pedido de plantones ──────────────────────────────────────────
add_action( 'wp_ajax_psp_crear_pedido_planton',        'psp_ajax_crear_pedido_planton' );
add_action( 'wp_ajax_nopriv_psp_crear_pedido_planton', 'psp_ajax_crear_pedido_planton' );
function psp_ajax_crear_pedido_planton() {
    if ( ! psp_verify_nonce() ) wp_send_json_error( ['message'=>'Nonce inválido'] );
    if ( ! psp_rate_limit('pedido_planton', 5, 60) ) wp_send_json_error( ['message'=>'Demasiados intentos'] );

    $jwt      = sanitize_text_field( $_POST['jwt']      ?? '' );
    $cantidad = max( 1, (int) ( $_POST['cantidad']       ?? 1 ) );

    if ( ! $jwt ) wp_send_json_error( ['message'=>'Debes iniciar sesión'] );

    $user_res  = wp_remote_get( PSP_SUPABASE_URL . '/auth/v1/user', [
        'headers' => [ 'apikey'=>PSP_SUPABASE_KEY, 'Authorization'=>'Bearer '.$jwt ],
    ]);
    $user_data = json_decode( wp_remote_retrieve_body( $user_res ), true );
    if ( empty( $user_data['id'] ) ) wp_send_json_error( ['message'=>'Token inválido'] );

    $miembro = PSP_Supabase::select( 'miembros', ['user_id'=>'eq.'.$user_data['id'],'limit'=>1] );
    if ( ! $miembro ) wp_send_json_error( ['message'=>'Miembro no encontrado. Regístrate primero.'] );

    $precio_unit = (float) get_option( 'psp_precio_planton', '2' );
    $total       = $cantidad * $precio_unit;
    $referencia  = 'PSP-PLANT-' . strtoupper( substr( bin2hex( random_bytes(4) ), 0, 8 ) );

    // Crear pago en Supabase
    $pago = PSP_Supabase::insert( 'pagos', [
        'miembro_id'    => $miembro[0]['id'],
        'monto'         => $total,
        'metodo'        => 'pendiente',
        'tipo_membresia'=> 'planton',
        'estado'        => 'pendiente',
        'referencia'    => $referencia,
        'tenant_id'     => get_option( 'psp_tenant_id', 'panama' ),
    ], true );

    if ( ! $pago ) wp_send_json_error( ['message'=>'Error creando pedido'] );

    // Registrar pedido de producto
    PSP_Supabase::insert( 'pedidos_productos', [
        'miembro_id'     => $miembro[0]['id'],
        'pago_id'        => $pago[0]['id'],
        'producto_slug'  => 'plantones',
        'producto_nombre'=> 'Plantones de Reforestación',
        'cantidad'       => $cantidad,
        'precio_unitario'=> $precio_unit,
        'total'          => $total,
        'referencia'     => $referencia,
        'estado'         => 'pendiente_pago',
        'tenant_id'      => get_option( 'psp_tenant_id', 'panama' ),
    ], true );

    wp_send_json_success([
        'pago_id'    => $pago[0]['id'],
        'referencia' => $referencia,
        'cantidad'   => $cantidad,
        'total'      => $total,
    ]);
}

// ── AJAX: crear solicitud SIGS ─────────────────────────────────────────────────
add_action( 'wp_ajax_psp_solicitar_sigs',        'psp_ajax_solicitar_sigs' );
add_action( 'wp_ajax_nopriv_psp_solicitar_sigs', 'psp_ajax_solicitar_sigs' );
function psp_ajax_solicitar_sigs() {
    if ( ! psp_verify_nonce() ) wp_send_json_error( ['message'=>'Nonce inválido'] );

    $data = psp_sanitize_input([
        'nombre'       => $_POST['nombre']        ?? '',
        'email'        => $_POST['email']         ?? '',
        'celular'      => $_POST['celular']        ?? '',
        'organizacion' => $_POST['organizacion']  ?? '',
        'plan'         => $_POST['plan']           ?? 'estandar',
        'descripcion'  => $_POST['descripcion']   ?? '',
        'jwt'          => $_POST['jwt']            ?? '',
    ]);

    if ( ! $data['nombre'] || ! $data['email'] ) {
        wp_send_json_error( ['message'=>'Nombre y email son obligatorios'] );
    }

    PSP_Supabase::insert( 'sigs_solicitudes', [
        'nombre'        => $data['nombre'],
        'email'         => $data['email'],
        'celular'       => $data['celular'],
        'organizacion'  => $data['organizacion'],
        'plan'          => $data['plan'],
        'descripcion'   => $data['descripcion'],
        'estado'        => 'nueva',
        'tenant_id'     => get_option('psp_tenant_id','panama'),
    ], true );

    // Notificar al admin por email
    wp_mail(
        get_option('admin_email'),
        'Nueva solicitud SIGS — ' . $data['organizacion'],
        'Nombre: ' . $data['nombre'] . "\nEmail: " . $data['email'] . "\nPlan: " . $data['plan'] . "\n\n" . $data['descripcion']
    );

    wp_send_json_success( ['message'=>'Solicitud enviada. Te contactaremos en menos de 24 horas.'] );
}

// ── AJAX: mis pedidos ────────────────────────────────────────────────────────
add_action( 'wp_ajax_psp_get_mis_pedidos',        'psp_ajax_get_mis_pedidos' );
add_action( 'wp_ajax_nopriv_psp_get_mis_pedidos', 'psp_ajax_get_mis_pedidos' );
function psp_ajax_get_mis_pedidos() {
    if ( ! psp_verify_nonce() ) wp_send_json_error();

    $jwt = sanitize_text_field( $_POST['jwt'] ?? '' );
    if ( ! $jwt ) wp_send_json_error( ['message'=>'JWT requerido'] );

    $user_res  = wp_remote_get( PSP_SUPABASE_URL . '/auth/v1/user', [
        'headers' => [ 'apikey'=>PSP_SUPABASE_KEY, 'Authorization'=>'Bearer '.$jwt ],
    ]);
    $user_data = json_decode( wp_remote_retrieve_body( $user_res ), true );
    if ( empty( $user_data['id'] ) ) wp_send_json_error();

    $miembro = PSP_Supabase::select( 'miembros', ['user_id'=>'eq.'.$user_data['id'],'select'=>'id','limit'=>1] );
    if ( ! $miembro ) wp_send_json_error();

    $pedidos = PSP_Supabase::select( 'pedidos_productos', [
        'miembro_id' => 'eq.' . $miembro[0]['id'],
        'order'      => 'created_at.desc',
        'limit'      => 50,
    ]);

    wp_send_json_success( $pedidos ?? [] );
}
