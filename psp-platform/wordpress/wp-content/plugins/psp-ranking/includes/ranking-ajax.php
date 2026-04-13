<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── AJAX: obtener ranking ─────────────────────────────────────────────────────
add_action( 'wp_ajax_psp_ranking_get',        'psp_ajax_ranking_get' );
add_action( 'wp_ajax_nopriv_psp_ranking_get', 'psp_ajax_ranking_get' );
function psp_ajax_ranking_get() {
    if ( ! psp_verify_nonce() ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido' ] );
    }

    if ( ! class_exists( 'PSP_Supabase' ) ) {
        wp_send_json_error( [ 'message' => 'PSP Core no activo' ] );
    }

    $tipo   = sanitize_text_field( $_POST['tipo']   ?? 'provincia' );
    $limite = min( (int) ( $_POST['limite'] ?? 20 ), 50 );

    // Cache 60 segundos
    $cache_key = 'psp_rank_' . $tipo . '_' . $limite;
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) {
        wp_send_json_success( $cached );
        return;
    }

    // Intentar función RPC de Supabase
    $data = PSP_Supabase::rpc( 'get_ranking', [
        'p_tipo'  => $tipo,
        'p_limit' => $limite,
    ] );

    // Fallback si no existe la función RPC
    if ( ! $data ) {
        $data = psp_ranking_fallback( $tipo, $limite );
    }

    set_transient( $cache_key, $data ?? [], 60 );
    wp_send_json_success( $data ?? [] );
}

/**
 * Fallback: ranking calculado directamente desde tablas
 */
function psp_ranking_fallback( $tipo, $limite ) {
    if ( ! class_exists( 'PSP_Supabase' ) ) return [];

    $counts = [];

    switch ( $tipo ) {
        case 'provincia':
            $rows = PSP_Supabase::select( 'miembros', [
                'select' => 'provincia_id,territorios(nombre)',
                'estado' => 'eq.activo',
                'limit'  => 9999,
            ] );
            if ( ! $rows ) return [];
            foreach ( $rows as $r ) {
                $nombre = isset( $r['territorios']['nombre'] ) ? $r['territorios']['nombre'] : '—';
                $counts[ $nombre ] = ( $counts[ $nombre ] ?? 0 ) + 1;
            }
            break;

        case 'pais':
            $rows = PSP_Supabase::select( 'miembros', [
                'select' => 'pais_id',
                'estado' => 'eq.activo',
                'limit'  => 9999,
            ] );
            if ( ! $rows ) return [];
            foreach ( $rows as $r ) {
                $p = $r['pais_id'] ?? 'PA';
                $counts[ $p ] = ( $counts[ $p ] ?? 0 ) + 1;
            }
            break;

        case 'embajador':
        case 'ciudad':
            $rows = PSP_Supabase::select( 'referidos_log', [
                'select' => 'referidor_id,miembros(nombre)',
                'limit'  => 9999,
            ] );
            if ( ! $rows ) return [];
            foreach ( $rows as $r ) {
                $nombre = isset( $r['miembros']['nombre'] ) ? $r['miembros']['nombre'] : '—';
                $counts[ $nombre ] = ( $counts[ $nombre ] ?? 0 ) + 1;
            }
            break;

        default:
            return [];
    }

    arsort( $counts );
    $counts = array_slice( $counts, 0, $limite, true );

    $result = [];
    foreach ( $counts as $nombre => $total ) {
        $result[] = [
            'nombre'      => $nombre,
            'total'       => $total,
            'monto_total' => 0,
        ];
    }
    return $result;
}

// ── AJAX: mi posición en el ranking ──────────────────────────────────────────
add_action( 'wp_ajax_psp_mi_posicion_ranking',        'psp_ajax_mi_posicion_ranking' );
add_action( 'wp_ajax_nopriv_psp_mi_posicion_ranking', 'psp_ajax_mi_posicion_ranking' );
function psp_ajax_mi_posicion_ranking() {
    if ( ! psp_verify_nonce() ) {
        wp_send_json_error();
    }

    if ( ! class_exists( 'PSP_Supabase' ) ) {
        wp_send_json_error( [ 'message' => 'PSP Core no activo' ] );
    }

    $jwt = sanitize_text_field( $_POST['jwt'] ?? '' );
    if ( ! $jwt ) {
        wp_send_json_error( [ 'message' => 'JWT requerido' ] );
    }

    // Verificar usuario
    $user_res  = wp_remote_get( PSP_SUPABASE_URL . '/auth/v1/user', [
        'headers' => [
            'apikey'        => PSP_SUPABASE_KEY,
            'Authorization' => 'Bearer ' . $jwt,
        ],
    ] );
    $user_data = json_decode( wp_remote_retrieve_body( $user_res ), true );

    if ( empty( $user_data['id'] ) ) {
        wp_send_json_error();
    }

    $miembro = PSP_Supabase::select( 'miembros', [
        'user_id' => 'eq.' . $user_data['id'],
        'select'  => 'id,nombre,puntos_total,nivel',
        'limit'   => 1,
    ] );
    if ( ! $miembro ) {
        wp_send_json_error( [ 'message' => 'Miembro no encontrado' ] );
    }

    $m = $miembro[0];

    // Contar referidos
    $refs = PSP_Supabase::select( 'referidos_log', [
        'referidor_id' => 'eq.' . $m['id'],
        'select'       => 'id',
        'limit'        => 9999,
    ] );

    $niveles_icon = [
        'Simpatizante' => '&#x1F331;',
        'Promotor'     => '&#x2B50;',
        'Embajador'    => '&#x1F31F;',
        'Líder'        => '&#x1F4AB;',
        'Champion'     => '&#x1F3C6;',
    ];
    $nivel      = $m['nivel'] ?? 'Simpatizante';
    $nivel_icon = $niveles_icon[ $nivel ] ?? '&#x1F331;';

    wp_send_json_success( [
        'posicion'   => 0,
        'puntos'     => (int) ( $m['puntos_total'] ?? 0 ),
        'referidos'  => count( $refs ?? [] ),
        'nivel'      => $nivel,
        'nivel_icon' => $nivel_icon,
    ] );
}
