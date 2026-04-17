<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── AJAX: mis facturas (usuario front-end) ────────────────────────────────────
add_action( 'wp_ajax_psp_get_mis_facturas',        'psp_ajax_get_mis_facturas' );
add_action( 'wp_ajax_nopriv_psp_get_mis_facturas', 'psp_ajax_get_mis_facturas' );
function psp_ajax_get_mis_facturas() {
    if ( ! psp_verify_nonce() ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido' ] );
    }

    $jwt = sanitize_text_field( $_POST['jwt'] ?? '' );
    if ( ! $jwt ) {
        wp_send_json_error( [ 'message' => 'JWT requerido' ] );
    }

    // Verificar usuario con Supabase Auth
    $user_res  = wp_remote_get( PSP_SUPABASE_URL . '/auth/v1/user', [
        'headers' => [
            'apikey'        => PSP_SUPABASE_KEY,
            'Authorization' => 'Bearer ' . $jwt,
        ],
    ] );
    $user_data = json_decode( wp_remote_retrieve_body( $user_res ), true );

    if ( empty( $user_data['id'] ) ) {
        wp_send_json_error( [ 'message' => 'Token inválido' ] );
    }

    // Obtener miembro
    $miembro = PSP_Supabase::select( 'miembros', [
        'user_id' => 'eq.' . $user_data['id'],
        'select'  => 'id',
        'limit'   => 1,
    ] );
    if ( ! $miembro ) {
        wp_send_json_error( [ 'message' => 'Miembro no encontrado' ] );
    }

    $miembro_id = $miembro[0]['id'];

    // Obtener pagos del miembro
    $pagos = PSP_Supabase::select( 'pagos', [
        'miembro_id' => 'eq.' . $miembro_id,
        'estado'     => 'eq.completado',
        'select'     => 'id,factura_id',
        'limit'      => 100,
    ] );

    if ( ! $pagos ) {
        wp_send_json_success( [] );
        return;
    }

    // Recopilar factura_ids válidos
    $factura_ids = array_filter( array_column( $pagos, 'factura_id' ) );
    if ( ! $factura_ids ) {
        wp_send_json_success( [] );
        return;
    }

    // Obtener facturas
    $facturas = [];
    foreach ( $factura_ids as $fid ) {
        $f = PSP_Supabase::select( 'facturas', [
            'id'    => 'eq.' . $fid,
            'limit' => 1,
        ] );
        if ( $f ) $facturas[] = $f[0];
    }

    wp_send_json_success( $facturas );
}

// ── AJAX: descargar XML de factura ────────────────────────────────────────────
add_action( 'wp_ajax_psp_descargar_factura',        'psp_ajax_descargar_factura' );
add_action( 'wp_ajax_nopriv_psp_descargar_factura', 'psp_ajax_descargar_factura' );
function psp_ajax_descargar_factura() {
    if ( ! psp_verify_nonce() ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido' ] );
    }

    $id = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) {
        wp_send_json_error( [ 'message' => 'ID requerido' ] );
    }

    $factura = PSP_Supabase::select( 'facturas', [
        'id'    => 'eq.' . $id,
        'limit' => 1,
    ] );

    if ( ! $factura ) {
        wp_send_json_error( [ 'message' => 'Factura no encontrada' ] );
    }

    wp_send_json_success( [
        'xml'            => $factura[0]['xml_content']   ?? '',
        'numero_factura' => $factura[0]['numero_factura'] ?? '',
    ] );
}

// ── AJAX: generar factura manualmente (solo admin) ────────────────────────────
add_action( 'wp_ajax_psp_generar_factura_manual', 'psp_ajax_generar_factura_manual' );
function psp_ajax_generar_factura_manual() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Sin permisos' ] );
    }
    if ( ! psp_verify_nonce() ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido' ] );
    }

    $pago_id = sanitize_text_field( $_POST['pago_id'] ?? '' );
    if ( ! $pago_id ) {
        wp_send_json_error( [ 'message' => 'pago_id requerido' ] );
    }

    $result = psp_procesar_factura_completa( $pago_id );
    if ( ! $result ) {
        wp_send_json_error( [ 'message' => 'Error generando factura. Verifica que psp-core esté activo y las credenciales Supabase configuradas.' ] );
    }

    wp_send_json_success( $result );
}
