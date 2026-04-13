<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── AJAX: obtener mi membresía ────────────────────────────────────────────────
add_action( 'wp_ajax_psp_get_mi_membresia',        'psp_ajax_get_mi_membresia' );
add_action( 'wp_ajax_nopriv_psp_get_mi_membresia', 'psp_ajax_get_mi_membresia' );
function psp_ajax_get_mi_membresia() {
    if ( ! psp_verify_nonce() ) wp_send_json_error( ['message'=>'Nonce inválido'] );

    $jwt = sanitize_text_field( $_POST['jwt'] ?? '' );
    if ( ! $jwt ) wp_send_json_error( ['message'=>'JWT requerido'] );

    $user_res  = wp_remote_get( PSP_SUPABASE_URL . '/auth/v1/user', [
        'headers' => [ 'apikey' => PSP_SUPABASE_KEY, 'Authorization' => 'Bearer ' . $jwt ],
    ] );
    $user_data = json_decode( wp_remote_retrieve_body( $user_res ), true );
    if ( empty( $user_data['id'] ) ) wp_send_json_error( ['message'=>'Token inválido'] );

    $miembro = PSP_Supabase::select( 'miembros', [
        'user_id' => 'eq.' . $user_data['id'],
        'select'  => 'id,nombre,tipo_miembro,estado,puntos_total,nivel,created_at',
        'limit'   => 1,
    ] );
    if ( ! $miembro ) wp_send_json_error( ['message'=>'Miembro no encontrado'] );

    $m = $miembro[0];

    // Contar referidos
    $refs = PSP_Supabase::select( 'referidos_log', [
        'referidor_id' => 'eq.' . $m['id'],
        'select'       => 'id',
        'limit'        => 9999,
    ] );
    $m['refs'] = count( $refs ?? [] );

    // Añadir icono según tipo
    $iconos = [
        'nacional'=>'&#x1F1F5;&#x1F1E6;','internacional'=>'&#x1F30E;','actor'=>'&#x1F3AD;',
        'sector'=>'&#x1F3E2;','hogar_solidario'=>'&#x1F3E0;','productor'=>'&#x1F33E;',
        'comunicador'=>'&#x1F4E2;','influencer'=>'&#x1F4F1;','embajador'=>'&#x1F31F;','voluntario'=>'&#x1F91D;',
    ];
    $m['icono'] = $iconos[ $m['tipo_miembro'] ?? '' ] ?? '&#x1F3F7;&#xFE0F;';

    wp_send_json_success( $m );
}

// ── AJAX: cambiar tipo de membresía (upgrade) ─────────────────────────────────
add_action( 'wp_ajax_psp_upgrade_membresia', 'psp_ajax_upgrade_membresia' );
function psp_ajax_upgrade_membresia() {
    if ( ! psp_verify_nonce() ) wp_send_json_error();
    $jwt  = sanitize_text_field( $_POST['jwt']            ?? '' );
    $tipo = sanitize_text_field( $_POST['nuevo_tipo']     ?? '' );
    if ( ! $jwt || ! $tipo ) wp_send_json_error( ['message'=>'Datos incompletos'] );

    $tipos_validos = array_column( psp_get_membresias_config(), 'tipo' );
    if ( ! in_array( $tipo, $tipos_validos ) ) wp_send_json_error( ['message'=>'Tipo inválido'] );

    $user_res  = wp_remote_get( PSP_SUPABASE_URL . '/auth/v1/user', [
        'headers' => [ 'apikey' => PSP_SUPABASE_KEY, 'Authorization' => 'Bearer ' . $jwt ],
    ] );
    $user_data = json_decode( wp_remote_retrieve_body( $user_res ), true );
    if ( empty( $user_data['id'] ) ) wp_send_json_error();

    $miembro = PSP_Supabase::select( 'miembros', [ 'user_id'=>'eq.'.$user_data['id'], 'limit'=>1 ] );
    if ( ! $miembro ) wp_send_json_error();

    PSP_Supabase::update( 'miembros', ['tipo_miembro'=>$tipo, 'estado'=>'pendiente_pago'], ['id'=>'eq.'.$miembro[0]['id']] );
    wp_send_json_success( ['nuevo_tipo'=>$tipo] );
}
