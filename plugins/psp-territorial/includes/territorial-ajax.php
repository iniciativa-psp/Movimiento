<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX: obtener territorios (fallback Supabase si no hay JSON externo)
 */
add_action( 'wp_ajax_psp_terr_get',        'psp_ajax_terr_get' );
add_action( 'wp_ajax_nopriv_psp_terr_get', 'psp_ajax_terr_get' );
function psp_ajax_terr_get() {
    if ( ! isset( $_POST['psp_nonce'] ) || ! wp_verify_nonce( $_POST['psp_nonce'], 'psp_nonce' ) ) {
        wp_send_json_error( ['message' => 'Nonce inválido'] );
    }

    $tipo      = sanitize_text_field( $_POST['tipo']      ?? '' );
    $parent_id = sanitize_text_field( $_POST['parent_id'] ?? '' );

    // Mapa tipo → tipo en DB
    $tipo_db_map = [
        'provincias'    => 'provincia',
        'distritos'     => 'distrito',
        'corregimientos'=> 'corregimiento',
        'comunidades'   => 'comunidad',
    ];
    $tipo_db = $tipo_db_map[ $tipo ] ?? $tipo;

    // Cache 1 hora
    $ckey  = 'psp_terr_' . $tipo . '_' . md5( $parent_id );
    $cache = get_transient( $ckey );
    if ( $cache !== false ) { wp_send_json_success( $cache ); return; }

    // 1. Intentar desde el plugin territorial externo (si expone REST o filter)
    $data_external = apply_filters( 'psp_territorial_get_data', null, $tipo, $parent_id );
    if ( $data_external !== null ) {
        set_transient( $ckey, $data_external, HOUR_IN_SECONDS );
        wp_send_json_success( $data_external );
        return;
    }

    // 2. Intentar desde archivo JSON (si está configurado)
    $json_path = get_option( 'psp_territorial_json_path', '' );
    if ( $json_path && file_exists( $json_path ) ) {
        $json  = file_get_contents( $json_path );
        $data  = json_decode( $json, true );
        $items = psp_terr_filtrar_json( $data, $tipo, $parent_id );
        if ( $items !== null ) {
            set_transient( $ckey, $items, HOUR_IN_SECONDS );
            wp_send_json_success( $items );
            return;
        }
    }

    // 3. Intentar desde URL JSON externa
    $json_url = get_option( 'psp_territorial_json_url', '' );
    if ( $json_url ) {
        $res = wp_remote_get( $json_url, [ 'timeout' => 10 ] );
        if ( ! is_wp_error( $res ) ) {
            $data  = json_decode( wp_remote_retrieve_body( $res ), true );
            $items = psp_terr_filtrar_json( $data, $tipo, $parent_id );
            if ( $items !== null ) {
                set_transient( $ckey, $items, HOUR_IN_SECONDS );
                wp_send_json_success( $items );
                return;
            }
        }
    }

    // 4. Fallback: Supabase
    if ( class_exists( 'PSP_Supabase' ) ) {
        $params = [ 'tipo' => 'eq.' . $tipo_db, 'activo' => 'eq.true', 'select' => 'id,nombre', 'order' => 'nombre.asc', 'limit' => 500 ];
        if ( $parent_id ) $params['parent_id'] = 'eq.' . $parent_id;
        $rows = PSP_Supabase::select( 'territorios', $params );
        $result = array_map( function($r){ return ['id'=>$r['id'],'nombre'=>$r['nombre']]; }, $rows ?? [] );
        set_transient( $ckey, $result, HOUR_IN_SECONDS );
        wp_send_json_success( $result );
        return;
    }

    // 5. Sin fuente disponible
    wp_send_json_success( [] );
}

/**
 * Filtra datos de un JSON según el formato más común
 */
function psp_terr_filtrar_json( $data, $tipo, $parent_id ) {
    if ( ! is_array( $data ) ) return null;

    // Formato { provincias:[...], distritos:[...], ... }
    if ( isset( $data[ $tipo ] ) && is_array( $data[ $tipo ] ) ) {
        $items = $data[ $tipo ];
        if ( $parent_id ) {
            $items = array_filter( $items, function( $i ) use ( $parent_id ) {
                $pid = $i['parent_id'] ?? $i['provincia_id'] ?? $i['distrito_id'] ?? $i['corregimiento_id'] ?? null;
                return (string) $pid === (string) $parent_id;
            });
        }
        return array_values( array_map( function($i){
            return [ 'id' => $i['id'] ?? $i['codigo'] ?? '', 'nombre' => $i['nombre'] ?? $i['name'] ?? '' ];
        }, $items ) );
    }

    // Formato array plano con campo tipo
    if ( isset( $data[0] ) && is_array( $data[0] ) ) {
        $items = array_filter( $data, function($i) use ($tipo) {
            $t = $i['tipo'] ?? '';
            return $t === $tipo || $t === rtrim($tipo,'s');
        });
        if ( $parent_id ) {
            $items = array_filter( $items, function($i) use ($parent_id) {
                return (string)($i['parent_id']??'') === (string)$parent_id;
            });
        }
        return array_values( array_map( function($i){
            return [ 'id'=>$i['id']??'', 'nombre'=>$i['nombre']??$i['name']??'' ];
        }, $items ) );
    }

    return null;
}

/**
 * AJAX: solicitar agregar nuevo territorio
 */
add_action( 'wp_ajax_psp_terr_solicitud',        'psp_ajax_terr_solicitud' );
add_action( 'wp_ajax_nopriv_psp_terr_solicitud', 'psp_ajax_terr_solicitud' );
function psp_ajax_terr_solicitud() {
    if ( ! isset( $_POST['psp_nonce'] ) || ! wp_verify_nonce( $_POST['psp_nonce'], 'psp_nonce' ) ) {
        wp_send_json_error( ['message' => 'Nonce inválido'] );
    }

    $nombre = sanitize_text_field( $_POST['nombre'] ?? '' );
    $tipo   = sanitize_text_field( $_POST['tipo']   ?? 'comunidad' );

    if ( ! $nombre ) wp_send_json_error( ['message' => 'Nombre requerido'] );

    if ( class_exists( 'PSP_Supabase' ) ) {
        PSP_Supabase::insert( 'territorios_solicitudes', [
            'nombre' => $nombre,
            'tipo'   => $tipo,
            'estado' => 'pendiente',
        ], true );
    }

    // Notificar admin
    wp_mail(
        get_option('admin_email'),
        'Solicitud nuevo territorio PSP: ' . $nombre,
        "Tipo: $tipo\nNombre: $nombre\nEstado: pendiente revisión"
    );

    wp_send_json_success( ['message' => 'Solicitud registrada'] );
}
